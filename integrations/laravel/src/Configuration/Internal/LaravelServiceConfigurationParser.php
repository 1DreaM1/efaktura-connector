<?php

namespace DreaM1\EfakturaConnector\Laravel\Configuration\Internal;

use DreaM1\EfakturaConnector\Core\Ubl\Contracts\UblValidationStageInterface;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Transport\Execution\HttpRequestExecutorInterface;
use DreaM1\EfakturaConnector\Laravel\Configuration\LaravelServiceConfiguration;
use DreaM1\EfakturaConnector\Support\ConfigurationValues;
use DreaM1\EfakturaConnector\Support\Exceptions\InvalidInputException;
use ReflectionClass;
use ReflectionException;

/** Parses and validates Laravel-specific connector settings. @internal */
final class LaravelServiceConfigurationParser
{
    /**
     * Parses the Laravel section into normalized settings for the configuration value object.
     *
     * @param array<string, mixed> $configuration Full package configuration array.
     * @return array{
     *     requestExecutorClass: class-string<HttpRequestExecutorInterface>,
     *     validationStageClasses: list<class-string<UblValidationStageInterface>>,
     *     serviceBindings: array<class-string, class-string>,
     *     httpLoggingEnabled: bool,
     *     httpMetricsEnabled: bool,
     *     cacheStore: string|null,
     *     executionLockDriver: string,
     *     executionLockStore: string|null,
     *     executionLockLeaseSeconds: int|null,
     *     executionLockWaitSeconds: int,
     *     executionLockTemporaryDirectory: string|null,
     *     validationResourceDirectory: string|null,
     *     validationWorkingDirectory: string|null,
     *     schematronConcurrencyLockDirectory: string|null,
     *     javaBinary: string|null
     * }
     * @throws InvalidInputException when a section, layer, path or implementation is invalid
     * @throws ReflectionException   when a configured class cannot be reflected
     */
    public static function parse(array $configuration): array
    {
        $laravel          = ConfigurationValues::section($configuration, 'laravel', 'laravel');
        $requestExecution = ConfigurationValues::section($laravel, 'request_execution', 'laravel.request_execution');
        $executionLock    = ConfigurationValues::section(
            $requestExecution,
            'execution_lock',
            'laravel.request_execution.execution_lock'
        );
        $validation = ConfigurationValues::section($laravel, 'validation', 'laravel.validation');
        $bindings   = ConfigurationValues::section($laravel, 'services', 'laravel.services');

        ConfigurationValues::assertAllowedKeys(
            $laravel,
            ['cache_store', 'request_execution', 'validation', 'services'],
            'laravel'
        );
        ConfigurationValues::assertAllowedKeys(
            $requestExecution,
            ['executor', 'logging', 'metrics', 'execution_lock'],
            'laravel.request_execution'
        );
        ConfigurationValues::assertAllowedKeys(
            $executionLock,
            ['driver', 'store', 'lease_seconds', 'wait_seconds', 'temporary_directory'],
            'laravel.request_execution.execution_lock'
        );
        ConfigurationValues::assertAllowedKeys(
            $validation,
            ['stages', 'resource_directory', 'working_directory', 'concurrency_lock_directory', 'java_binary'],
            'laravel.validation'
        );

        $executor           = self::parseRequestExecutorClass($requestExecution);
        $normalizedStages   = self::parseValidationStageClasses($validation);
        $normalizedBindings = self::parseServiceBindings($bindings);

        $executionLockTemporaryDirectory = self::optionalString(
            $executionLock,
            'temporary_directory',
            'laravel.request_execution.execution_lock.temporary_directory'
        );

        return [
            'requestExecutorClass'   => $executor,
            'validationStageClasses' => $normalizedStages,
            'serviceBindings'        => $normalizedBindings,
            'httpLoggingEnabled'     => ConfigurationValues::boolean(
                $requestExecution['logging'] ?? true,
                'laravel.request_execution.logging'
            ),
            'httpMetricsEnabled' => ConfigurationValues::boolean(
                $requestExecution['metrics'] ?? true,
                'laravel.request_execution.metrics'
            ),
            'cacheStore'          => self::optionalString($laravel, 'cache_store', 'laravel.cache_store'),
            'executionLockDriver' => self::parseExecutionLockDriver(
                $executionLock,
                $executionLockTemporaryDirectory
            ),
            'executionLockStore' => self::optionalString(
                $executionLock,
                'store',
                'laravel.request_execution.execution_lock.store'
            ),
            'executionLockLeaseSeconds' => self::optionalPositiveInteger(
                $executionLock,
                'lease_seconds',
                'laravel.request_execution.execution_lock.lease_seconds'
            ),
            'executionLockWaitSeconds' => self::nonNegativeInteger(
                $executionLock,
                'wait_seconds',
                LaravelServiceConfiguration::DEFAULT_EXECUTION_LOCK_WAIT_SECONDS,
                'laravel.request_execution.execution_lock.wait_seconds'
            ),
            'executionLockTemporaryDirectory' => $executionLockTemporaryDirectory,
            'validationResourceDirectory'     => self::optionalString(
                $validation,
                'resource_directory',
                'laravel.validation.resource_directory'
            ),
            'validationWorkingDirectory' => self::optionalString(
                $validation,
                'working_directory',
                'laravel.validation.working_directory'
            ),
            'schematronConcurrencyLockDirectory' => self::optionalString(
                $validation,
                'concurrency_lock_directory',
                'laravel.validation.concurrency_lock_directory'
            ),
            'javaBinary' => self::optionalString(
                $validation,
                'java_binary',
                'laravel.validation.java_binary'
            ),
        ];
    }

    /**
     * Resolves and validates the configured base HTTP request executor.
     *
     * @param  array<string, mixed>                       $requestExecution Request execution settings from Laravel configuration.
     * @return class-string<HttpRequestExecutorInterface> Instantiable executor class name.
     * @throws InvalidInputException                      when the executor setting is missing or invalid.
     * @throws ReflectionException                        when the configured executor class cannot be reflected.
     */
    private static function parseRequestExecutorClass(array $requestExecution): string
    {
        $executor           = $requestExecution['executor'] ?? LaravelServiceConfiguration::DEFAULT_REQUEST_EXECUTOR_CLASS;
        $normalizedExecutor = is_string($executor) ? trim($executor) : '';

        if ($normalizedExecutor === '') {
            throw new InvalidInputException(
                'The laravel.request_execution.executor setting must name an HttpRequestExecutorInterface implementation.'
            );
        }

        if (! is_a($normalizedExecutor, HttpRequestExecutorInterface::class, true)) {
            throw self::notImplementation(HttpRequestExecutorInterface::class, 'request executor');
        }

        self::assertInstantiable($normalizedExecutor, HttpRequestExecutorInterface::class, 'request executor');

        return $normalizedExecutor;
    }

    /**
     * Resolves the execution-lock backend and validates file-lock directory compatibility.
     *
     * @param  array<string, mixed>  $executionLock      Execution-lock settings from Laravel configuration.
     * @param  string|null           $temporaryDirectory Optional directory used by the file-lock backend.
     * @return string                Selected `cache` or `file` backend.
     * @throws InvalidInputException when the driver is unsupported or incompatible with the directory setting.
     */
    private static function parseExecutionLockDriver(array $executionLock, ?string $temporaryDirectory): string
    {
        $driver = $executionLock['driver'] ?? LaravelServiceConfiguration::DEFAULT_EXECUTION_LOCK_DRIVER;
        $driver = is_string($driver) ? trim($driver) : '';

        $drivers = [
            LaravelServiceConfiguration::EXECUTION_LOCK_DRIVER_CACHE,
            LaravelServiceConfiguration::EXECUTION_LOCK_DRIVER_FILE,
        ];

        if (! in_array($driver, $drivers, true)) {
            throw new InvalidInputException(
                'The laravel.request_execution.execution_lock.driver setting must be "cache" or "file".'
            );
        }

        if ($temporaryDirectory !== null && $driver !== LaravelServiceConfiguration::EXECUTION_LOCK_DRIVER_FILE) {
            throw new InvalidInputException(
                'The laravel.request_execution.execution_lock.temporary_directory setting requires driver="file".'
            );
        }

        return $driver;
    }

    /**
     * Reads a configured positive integer or returns its default.
     *
     * @param  array<string, mixed>  $section Configuration subsection containing the setting.
     * @param  string                $key     Setting key within the subsection.
     * @param  int                   $default Value used when the key is absent.
     * @param  string                $path    Full setting path used in validation errors.
     * @return int                   Validated value greater than zero.
     * @throws InvalidInputException when the configured value is invalid or less than one.
     */
    private static function positiveInteger(array $section, string $key, int $default, string $path): int
    {
        $value = ConfigurationValues::integer($section, $key, $default, $path);

        if ($value < 1) {
            throw new InvalidInputException('The ' . $path . ' configuration must be at least 1.');
        }

        return $value;
    }

    /**
     * Reads an optional positive integer; an absent, null or empty value means the setting is not configured.
     *
     * @param  array<string, mixed>  $section Configuration subsection containing the setting.
     * @param  string                $key     Setting key within the subsection.
     * @param  string                $path    Full setting path used in validation errors.
     * @return int|null              Validated value, or null when the setting is not configured.
     * @throws InvalidInputException when the configured value is not a positive integer.
     */
    private static function optionalPositiveInteger(array $section, string $key, string $path): ?int
    {
        if (($section[$key] ?? null) === null || $section[$key] === '') {
            return null;
        }

        return self::positiveInteger($section, $key, 1, $path);
    }

    /**
     * Reads a configured non-negative integer or returns its default.
     *
     * @param  array<string, mixed>  $section Configuration subsection containing the setting.
     * @param  string                $key     Setting key within the subsection.
     * @param  int                   $default Value used when the key is absent.
     * @param  string                $path    Full setting path used in validation errors.
     * @return int                   Validated value greater than or equal to zero.
     * @throws InvalidInputException when the configured value is invalid or negative.
     */
    private static function nonNegativeInteger(array $section, string $key, int $default, string $path): int
    {
        $value = ConfigurationValues::integer($section, $key, $default, $path);

        if ($value < 0) {
            throw new InvalidInputException('The ' . $path . ' configuration must be zero or greater.');
        }

        return $value;
    }

    /**
     * @param  array<string, mixed>                            $validation Validation settings from Laravel configuration.
     * @return list<class-string<UblValidationStageInterface>> Validated stage classes in execution order.
     * @throws InvalidInputException                           when the stage list is empty or a stage is invalid or incompatible.
     * @throws ReflectionException                             when a configured stage class cannot be reflected.
     */
    private static function parseValidationStageClasses(array $validation): array
    {
        $stages = $validation['stages'] ?? LaravelServiceConfiguration::DEFAULT_VALIDATION_STAGE_CLASSES;

        if (! is_array($stages)) {
            throw new InvalidInputException('The laravel.validation.stages setting must be an array.');
        }

        if ($stages === []) {
            throw new InvalidInputException('The laravel.validation.stages setting must contain at least one stage.');
        }

        $normalizedStages = [];

        foreach ($stages as $stage) {
            $normalizedStage = is_string($stage) ? trim($stage) : '';

            if ($normalizedStage === '') {
                throw new InvalidInputException(
                    'Every laravel.validation.stages entry must implement UblValidationStageInterface.'
                );
            }

            if (! is_a($normalizedStage, UblValidationStageInterface::class, true)) {
                throw self::notImplementation(UblValidationStageInterface::class, 'validation stage');
            }

            self::assertInstantiable($normalizedStage, UblValidationStageInterface::class, 'validation stage');
            $normalizedStages[] = $normalizedStage;
        }

        return $normalizedStages;
    }

    /**
     * @param  array<string, mixed>              $bindings Service contract to implementation class settings.
     * @return array<class-string, class-string> Validated implementation classes keyed by contract.
     * @throws InvalidInputException             when a binding is blank or does not implement its contract.
     * @throws ReflectionException               when a configured implementation class cannot be reflected.
     */
    private static function parseServiceBindings(array $bindings): array
    {
        $normalizedBindings = [];

        foreach ($bindings as $contract => $implementation) {
            $normalizedContract       = is_string($contract) ? trim($contract) : '';
            $normalizedImplementation = is_string($implementation) ? trim($implementation) : '';

            if ($normalizedContract === '' || $normalizedImplementation === '') {
                throw new InvalidInputException(
                    'Every Laravel services entry must map a service contract to a compatible class.'
                );
            }

            if (
                (! class_exists($normalizedContract) && ! interface_exists($normalizedContract))
                || ! class_exists($normalizedImplementation)
                || ! is_a($normalizedImplementation, $normalizedContract, true)
            ) {
                throw self::notImplementation($normalizedContract, 'service binding');
            }

            self::assertInstantiable($normalizedImplementation, $normalizedContract, 'service binding');
            $normalizedBindings[$normalizedContract] = $normalizedImplementation;
        }

        return $normalizedBindings;
    }

    /**
     * Checks that a configured class that already implements its contract can be instantiated.
     *
     * @param  class-string          $implementation Configured implementation class name.
     * @param  string                $contract       Interface or parent class the class implements.
     * @param  string                $setting        Setting description used in the validation error.
     * @throws InvalidInputException when the class is not instantiable
     * @throws ReflectionException   when the configured class cannot be reflected
     */
    private static function assertInstantiable(string $implementation, string $contract, string $setting): void
    {
        if (! (new ReflectionClass($implementation))->isInstantiable()) {
            throw self::notImplementation($contract, $setting);
        }
    }

    /** Builds the error for a configured class that is not an instantiable implementation of its contract. */
    private static function notImplementation(string $contract, string $setting): InvalidInputException
    {
        return new InvalidInputException(
            'The configured ' . $setting . ' must name an instantiable implementation of ' . $contract . '.'
        );
    }

    /**
     * Reads an optional non-blank path or executable name.
     *
     * @param  array<string, mixed>  $section Configuration subsection containing the value.
     * @param  string                $key     Setting name in the subsection.
     * @param  string                $path    Full configuration path used in an error message.
     * @return string|null           Trimmed value, or null when omitted or set to an empty string.
     * @throws InvalidInputException when a supplied value is not a non-blank string
     */
    private static function optionalString(array $section, string $key, string $path): ?string
    {
        $value = $section[$key] ?? null;

        if ($value === null || $value === '') {
            return null;
        }

        $value = is_string($value) ? trim($value) : '';

        if ($value === '') {
            throw new InvalidInputException('The ' . $path . ' setting must be a non-empty string.');
        }

        return $value;
    }
}
