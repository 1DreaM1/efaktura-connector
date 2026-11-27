<?php

namespace DreaM1\EfakturaConnector\Laravel\Configuration;

use DreaM1\EfakturaConnector\Core\Ubl\Contracts\UblValidationStageInterface;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Transport\Execution\CurlRequestExecutor;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Transport\Execution\HttpRequestExecutorInterface;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Validation\PeppolBillingHeaderValidationStage;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Validation\Schema\UblSchemaValidationStage;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Validation\Schematron\PeppolSchematronValidationStage;
use DreaM1\EfakturaConnector\Laravel\Configuration\Internal\LaravelServiceConfigurationParser;
use DreaM1\EfakturaConnector\Support\Exceptions\InvalidInputException;
use ReflectionException;

/**
 * Validated Laravel-specific container and local validation settings.
 *
 * This class holds choices that depend on the host framework, such as container bindings and
 * directory paths. Provider credentials, HTTP policies and process limits belong to the Core
 * configuration classes instead.
 */
final class LaravelServiceConfiguration
{
    /** Default base executor used for outbound HTTP requests. */
    public const DEFAULT_REQUEST_EXECUTOR_CLASS = CurlRequestExecutor::class;
    /** Execution-lock backend that uses Laravel cache atomic locks. */
    public const EXECUTION_LOCK_DRIVER_CACHE = 'cache';
    /** Execution-lock backend that uses local lock files. */
    public const EXECUTION_LOCK_DRIVER_FILE = 'file';
    /** Default coordination backend used for Laravel HTTP request locks. */
    public const DEFAULT_EXECUTION_LOCK_DRIVER = self::EXECUTION_LOCK_DRIVER_CACHE;
    /** Default maximum wait to acquire a provider-scoped request lock, in seconds. */
    public const DEFAULT_EXECUTION_LOCK_WAIT_SECONDS = 120;
    /**
     * Default local validation stages, executed in this order.
     * @var list<class-string<UblValidationStageInterface>>
     */
    public const DEFAULT_VALIDATION_STAGE_CLASSES = [
        UblSchemaValidationStage::class,
        PeppolBillingHeaderValidationStage::class,
        PeppolSchematronValidationStage::class,
    ];
    /** @var class-string<HttpRequestExecutorInterface> */
    private string $requestExecutorClass;
    /** @var list<class-string<UblValidationStageInterface>> */
    private array $validationStageClasses;
    /** @var array<class-string, class-string> */
    private array $serviceBindings;
    private bool $httpLoggingEnabled;
    private bool $httpMetricsEnabled;
    private ?string $cacheStore;
    private string $executionLockDriver;
    private ?string $executionLockStore;
    private ?int $executionLockLeaseSeconds;
    private int $executionLockWaitSeconds;
    private ?string $executionLockTemporaryDirectory;
    private ?string $validationResourceDirectory;
    private ?string $validationWorkingDirectory;
    private ?string $schematronConcurrencyLockDirectory;
    private ?string $javaBinary;

    /**
     * Creates an immutable snapshot of validated Laravel service choices.
     *
     * @param class-string<HttpRequestExecutorInterface>      $requestExecutorClass               HTTP executor implementation class.
     * @param list<class-string<UblValidationStageInterface>> $validationStageClasses
     *                                                                                            Validation stages to register in order.
     * @param array<class-string, class-string>               $serviceBindings                    Map of service contracts to implementation classes.
     * @param bool                                            $httpLoggingEnabled                 Whether the HTTP execution logger is attached when one is available.
     * @param bool                                            $httpMetricsEnabled                 Whether HTTP metrics are attached when a metrics service is bound.
     * @param string|null                                     $cacheStore                         Shared Laravel cache store; null selects the default store.
     * @param string                                          $executionLockDriver                Laravel cache lock or file-lock backend.
     * @param string|null                                     $executionLockStore                 Laravel cache store name; null selects the default store.
     * @param int|null                                        $executionLockLeaseSeconds          Explicit cache-lock lease in seconds; null derives it from the request timeout.
     * @param int                                             $executionLockWaitSeconds           Maximum wait to acquire the cache lock, in seconds.
     * @param string|null                                     $executionLockTemporaryDirectory    Directory for cross-process request lock files.
     * @param string|null                                     $validationResourceDirectory        Directory containing XSD, Schematron and runtime resources.
     * @param string|null                                     $validationWorkingDirectory         Working directory for local validation processes and files.
     * @param string|null                                     $schematronConcurrencyLockDirectory Directory for Schematron concurrency lock files.
     * @param string|null                                     $javaBinary                         Java executable name or path used to run Schematron validation.
     */
    private function __construct(
        string $requestExecutorClass,
        array $validationStageClasses,
        array $serviceBindings,
        bool $httpLoggingEnabled,
        bool $httpMetricsEnabled,
        ?string $cacheStore,
        string $executionLockDriver,
        ?string $executionLockStore,
        ?int $executionLockLeaseSeconds,
        int $executionLockWaitSeconds,
        ?string $executionLockTemporaryDirectory,
        ?string $validationResourceDirectory,
        ?string $validationWorkingDirectory,
        ?string $schematronConcurrencyLockDirectory,
        ?string $javaBinary
    ) {
        $this->requestExecutorClass               = $requestExecutorClass;
        $this->validationStageClasses             = $validationStageClasses;
        $this->serviceBindings                    = $serviceBindings;
        $this->httpLoggingEnabled                 = $httpLoggingEnabled;
        $this->httpMetricsEnabled                 = $httpMetricsEnabled;
        $this->cacheStore                         = $cacheStore;
        $this->executionLockDriver                = $executionLockDriver;
        $this->executionLockStore                 = $executionLockStore;
        $this->executionLockLeaseSeconds          = $executionLockLeaseSeconds;
        $this->executionLockWaitSeconds           = $executionLockWaitSeconds;
        $this->executionLockTemporaryDirectory    = $executionLockTemporaryDirectory;
        $this->validationResourceDirectory        = $validationResourceDirectory;
        $this->validationWorkingDirectory         = $validationWorkingDirectory;
        $this->schematronConcurrencyLockDirectory = $schematronConcurrencyLockDirectory;
        $this->javaBinary                         = $javaBinary;
    }

    /**
     * Parses and validates the `laravel` section of the package configuration.
     *
     * Configured executor, validation stage and service classes must be instantiable and implement
     * their declared contract. Missing optional choices use the package defaults.
     * The Laravel settings include a shared cache store and the nested groups `request_execution`,
     * `validation` and `services`; service entries map contract class names to implementation class names.
     *
     * @param  array<string, mixed>  $configuration Full package configuration array.
     * @throws InvalidInputException when a section, layer, path or implementation is invalid
     * @throws ReflectionException   when a configured class cannot be reflected
     */
    public static function fromArray(array $configuration): self
    {
        $values = LaravelServiceConfigurationParser::parse($configuration);

        return new self(
            $values['requestExecutorClass'],
            $values['validationStageClasses'],
            $values['serviceBindings'],
            $values['httpLoggingEnabled'],
            $values['httpMetricsEnabled'],
            $values['cacheStore'],
            $values['executionLockDriver'],
            $values['executionLockStore'],
            $values['executionLockLeaseSeconds'],
            $values['executionLockWaitSeconds'],
            $values['executionLockTemporaryDirectory'],
            $values['validationResourceDirectory'],
            $values['validationWorkingDirectory'],
            $values['schematronConcurrencyLockDirectory'],
            $values['javaBinary']
        );
    }

    /**
     * Returns the configured HTTP request executor implementation class name.
     *
     * @return class-string<HttpRequestExecutorInterface>
     */
    public function requestExecutorClass(): string
    {
        return $this->requestExecutorClass;
    }

    /**
     * Returns configured validation stage classes in execution order.
     *
     * @return list<class-string<UblValidationStageInterface>> Validated stage classes in execution order.
     */
    public function validationStageClasses(): array
    {
        return $this->validationStageClasses;
    }

    /**
     * Returns host overrides keyed by service contract class name.
     *
     * @return array<class-string, class-string>
     */
    public function serviceBindings(): array
    {
        return $this->serviceBindings;
    }

    /** Returns whether the host replaced the package binding for a service contract. */
    public function overrides(string $contract): bool
    {
        return array_key_exists($contract, $this->serviceBindings);
    }

    /** Returns whether the HTTP execution logger may be attached (it also needs a bound logger service). */
    public function httpLoggingEnabled(): bool
    {
        return $this->httpLoggingEnabled;
    }

    /** Returns whether HTTP metrics may be attached (they also need a bound metrics service). */
    public function httpMetricsEnabled(): bool
    {
        return $this->httpMetricsEnabled;
    }

    /** Returns the configured HTTP lock backend (`cache` or `file`). */
    public function executionLockDriver(): string
    {
        return $this->executionLockDriver;
    }

    /** Returns the HTTP lock cache store override, or the shared Laravel cache store. */
    public function executionLockStore(): ?string
    {
        return $this->executionLockStore ?? $this->cacheStore;
    }

    /** Returns the cache store shared by Laravel-backed connector state, or Laravel's default store. */
    public function cacheStore(): ?string
    {
        return $this->cacheStore;
    }

    /** Returns the explicitly configured cache-lock lease in seconds, or null when it is derived from the request timeout. */
    public function executionLockLeaseSeconds(): ?int
    {
        return $this->executionLockLeaseSeconds;
    }

    /** Returns the maximum wait for a cache lock in seconds. */
    public function executionLockWaitSeconds(): int
    {
        return $this->executionLockWaitSeconds;
    }

    /** Returns the configured validation resource directory, or null to use bundled resources. */
    public function validationResourceDirectory(): ?string
    {
        return $this->validationResourceDirectory;
    }

    /** Returns the temporary directory for cross-process request execution locks, if configured. */
    public function executionLockTemporaryDirectory(): ?string
    {
        return $this->executionLockTemporaryDirectory;
    }

    /** Returns the working directory for local validation files, if configured. */
    public function validationWorkingDirectory(): ?string
    {
        return $this->validationWorkingDirectory;
    }

    /** Returns the directory for Schematron concurrency lock files, if configured. */
    public function schematronConcurrencyLockDirectory(): ?string
    {
        return $this->schematronConcurrencyLockDirectory;
    }

    /** Returns the Java executable name or path for local Schematron validation, if configured. */
    public function javaBinary(): ?string
    {
        return $this->javaBinary;
    }
}
