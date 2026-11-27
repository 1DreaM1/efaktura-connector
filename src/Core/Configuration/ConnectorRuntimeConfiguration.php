<?php

namespace DreaM1\EfakturaConnector\Core\Configuration;

use DreaM1\EfakturaConnector\Core\CircuitBreaker\CircuitBreakerPolicy;
use DreaM1\EfakturaConnector\Core\Configuration\Internal\HttpRuntimeSettings;
use DreaM1\EfakturaConnector\Core\Configuration\Internal\LocalValidationRuntimeSettings;
use DreaM1\EfakturaConnector\Core\Exceptions\ConfigurationException;
use DreaM1\EfakturaConnector\Core\Retry\RetryPolicy;
use DreaM1\EfakturaConnector\Support\ConfigurationValues;
use DreaM1\EfakturaConnector\Support\Exceptions\InvalidInputException;

/**
 * Immutable framework-independent HTTP and local validation runtime settings.
 *
 * Values are parsed from the `runtime` configuration section and validated when the instance is
 * created. Provider identity and dependency-injection choices are configured separately.
 */
final class ConnectorRuntimeConfiguration implements ConnectorRuntimeConfigurationInterface
{
    /** Default maximum connection establishment time, in seconds. */
    public const DEFAULT_HTTP_CONNECT_TIMEOUT_SECONDS = 10;
    /** Default maximum duration of one HTTP request, in seconds. */
    public const DEFAULT_HTTP_REQUEST_TIMEOUT_SECONDS = 45;
    /** Default maximum accepted HTTP response body size, in bytes. */
    public const DEFAULT_HTTP_RESPONSE_MAX_BYTES = HttpResponseBodyLimit::DEFAULT_BYTES;
    /** Default maximum encoded HTTP request body size, in bytes. */
    public const DEFAULT_HTTP_REQUEST_MAX_BYTES = HttpRequestBodyLimit::DEFAULT_BYTES;
    /** Default cooldown for a 429 response without a usable Retry-After value, in seconds. */
    public const DEFAULT_HTTP_RATE_LIMIT_COOLDOWN_SECONDS = 1;
    /** Default maximum duration of one local Schematron process, in seconds. */
    public const DEFAULT_SCHEMATRON_PROCESS_TIMEOUT_SECONDS = 60;
    /** Default maximum number of local Schematron validation batches running concurrently. */
    public const DEFAULT_SCHEMATRON_MAX_CONCURRENT_VALIDATIONS = 2;
    /** Default maximum number of findings retained from each validation stage. */
    public const DEFAULT_VALIDATION_MAX_ISSUES = 8;
    /** Default maximum Schematron report size accepted from the local process, in bytes. */
    public const DEFAULT_SCHEMATRON_MAX_REPORT_BYTES = 32 * 1024 * 1024;
    /** Default maximum captured error output from the local Schematron process, in bytes. */
    public const DEFAULT_SCHEMATRON_MAX_ERROR_OUTPUT_BYTES = 64 * 1024;
    /** Default maximum characters retained for one Schematron process error detail. */
    public const DEFAULT_SCHEMATRON_MAX_ERROR_DETAIL_CHARACTERS = 512;
    /** Absolute upper bound for configured findings retained from a validation stage. */
    public const MAX_CONFIGURED_VALIDATION_ISSUES = 1000;
    /** Absolute upper bound for the HTTP response body limit, in bytes. */
    public const MAX_CONFIGURED_HTTP_RESPONSE_BYTES = HttpResponseBodyLimit::MAX_BYTES;
    /** Absolute upper bound for the HTTP request body limit, in bytes. */
    public const MAX_CONFIGURED_HTTP_REQUEST_BYTES = HttpRequestBodyLimit::MAX_BYTES;
    /** Absolute upper bound for accepted Schematron report size, in bytes. */
    public const MAX_CONFIGURED_SCHEMATRON_REPORT_BYTES = 128 * 1024 * 1024;
    /** Absolute upper bound for captured Schematron process error output, in bytes. */
    public const MAX_CONFIGURED_SCHEMATRON_ERROR_OUTPUT_BYTES = 1024 * 1024;
    /** Absolute upper bound for retained characters in one Schematron process error detail. */
    public const MAX_CONFIGURED_SCHEMATRON_ERROR_DETAIL_CHARACTERS = 4096;
    /** Smallest Java heap limit accepted for a Schematron process, in megabytes. */
    public const MIN_CONFIGURED_SCHEMATRON_JAVA_HEAP_MB = 64;
    /** Largest Java heap limit accepted for a Schematron process, in megabytes. */
    public const MAX_CONFIGURED_SCHEMATRON_JAVA_HEAP_MB = 65536;

    private HttpRuntimeSettings $httpSettings;
    private RetryPolicy $retryPolicy;
    private bool $retryEnabled;
    private CircuitBreakerPolicy $circuitBreakerPolicy;
    private bool $circuitBreakerEnabled;
    private bool $backpressureEnabled;
    private bool $executionLockEnabled;
    private int $rateLimitFallbackCooldownSeconds;
    private LocalValidationRuntimeSettings $localValidationSettings;

    /**
     * Creates runtime settings from grouped values and checks their supported ranges.
     *
     * @param  HttpRuntimeSettings            $httpSettings                     HTTP timeouts and response limit.
     * @param  RetryPolicy                    $retryPolicy                      Retry attempts and delay policy.
     * @param  bool                           $retryEnabled                     Whether eligible requests may be retried.
     * @param  CircuitBreakerPolicy           $circuitBreakerPolicy             Provider circuit-breaker thresholds and timings.
     * @param  bool                           $circuitBreakerEnabled            Whether the circuit-breaker layer is enabled.
     * @param  bool                           $backpressureEnabled              Whether the shared 429 cooldown layer is enabled.
     * @param  bool                           $executionLockEnabled             Whether requests are serialized per rate-limit scope (effective only with backpressure).
     * @param  int                            $rateLimitFallbackCooldownSeconds Cooldown when a 429 has no valid Retry-After.
     * @param  LocalValidationRuntimeSettings $localValidationSettings          Local validation limits.
     * @throws ConfigurationException         when a value is outside its supported range
     */
    private function __construct(
        HttpRuntimeSettings $httpSettings,
        RetryPolicy $retryPolicy,
        bool $retryEnabled,
        CircuitBreakerPolicy $circuitBreakerPolicy,
        bool $circuitBreakerEnabled,
        bool $backpressureEnabled,
        bool $executionLockEnabled,
        int $rateLimitFallbackCooldownSeconds,
        LocalValidationRuntimeSettings $localValidationSettings
    ) {
        self::assertPositive($httpSettings->connectTimeoutSeconds(), 'HTTP connect timeout');
        self::assertPositive($httpSettings->requestTimeoutSeconds(), 'HTTP request timeout');

        if ($httpSettings->connectTimeoutSeconds() > $httpSettings->requestTimeoutSeconds()) {
            throw new ConfigurationException('HTTP connect timeout must not exceed the total request timeout.');
        }

        self::assertPositive($localValidationSettings->processTimeoutSeconds(), 'Schematron process timeout');
        self::assertPositive(
            $localValidationSettings->maxConcurrentValidations(),
            'Maximum concurrent Schematron validations'
        );
        self::assertPositive($rateLimitFallbackCooldownSeconds, 'HTTP rate limit fallback cooldown');
        self::assertPositive($localValidationSettings->maxIssues(), 'Maximum validation issues');
        self::assertPositive($localValidationSettings->maxReportBytes(), 'Maximum Schematron report size');
        self::assertPositive(
            $localValidationSettings->maxErrorOutputBytes(),
            'Maximum Schematron error output size'
        );
        self::assertPositive(
            $localValidationSettings->maxErrorDetailCharacters(),
            'Maximum Schematron error detail length'
        );
        self::assertAtMost(
            $localValidationSettings->maxIssues(),
            self::MAX_CONFIGURED_VALIDATION_ISSUES,
            'Maximum validation issues'
        );
        self::assertAtMost(
            $localValidationSettings->maxReportBytes(),
            self::MAX_CONFIGURED_SCHEMATRON_REPORT_BYTES,
            'Maximum Schematron report size'
        );
        self::assertAtMost(
            $localValidationSettings->maxErrorOutputBytes(),
            self::MAX_CONFIGURED_SCHEMATRON_ERROR_OUTPUT_BYTES,
            'Maximum Schematron error output size'
        );
        self::assertAtMost(
            $localValidationSettings->maxErrorDetailCharacters(),
            self::MAX_CONFIGURED_SCHEMATRON_ERROR_DETAIL_CHARACTERS,
            'Maximum Schematron error detail length'
        );

        $heapLimit = $localValidationSettings->javaMaxHeapMegabytes();

        if (
            $heapLimit !== null
            && ($heapLimit < self::MIN_CONFIGURED_SCHEMATRON_JAVA_HEAP_MB || $heapLimit > self::MAX_CONFIGURED_SCHEMATRON_JAVA_HEAP_MB)
        ) {
            throw new ConfigurationException(sprintf(
                'Schematron Java heap limit must be between %d and %d megabytes.',
                self::MIN_CONFIGURED_SCHEMATRON_JAVA_HEAP_MB,
                self::MAX_CONFIGURED_SCHEMATRON_JAVA_HEAP_MB
            ));
        }

        $this->httpSettings                     = $httpSettings;
        $this->retryPolicy                      = $retryPolicy;
        $this->retryEnabled                     = $retryEnabled;
        $this->circuitBreakerPolicy             = $circuitBreakerPolicy;
        $this->circuitBreakerEnabled            = $circuitBreakerEnabled;
        $this->backpressureEnabled              = $backpressureEnabled;
        $this->executionLockEnabled             = $backpressureEnabled && $executionLockEnabled;
        $this->rateLimitFallbackCooldownSeconds = $rateLimitFallbackCooldownSeconds;
        $this->localValidationSettings          = $localValidationSettings;
    }

    /**
     * Creates runtime settings from the `runtime` section.
     *
     * @param  array<string, mixed>   $configuration Full package configuration array.
     * @throws ConfigurationException when a section has an invalid shape or a setting is out of range
     */
    public static function fromArray(array $configuration): self
    {
        try {
            $sections = self::readRuntimeSections($configuration);
            self::assertRuntimeSectionKeys($sections);

            return self::createFromRuntimeSections($sections);
        } catch (InvalidInputException $exception) {
            throw new ConfigurationException($exception->getMessage(), $exception);
        }
    }

    /** Returns the maximum connection establishment time in seconds. */
    public function httpConnectTimeoutSeconds(): int
    {
        return $this->httpSettings->connectTimeoutSeconds();
    }

    /** Returns the maximum total HTTP request time in seconds. */
    public function httpRequestTimeoutSeconds(): int
    {
        return $this->httpSettings->requestTimeoutSeconds();
    }

    /** Returns the configured HTTP response body limit as a typed value. */
    public function httpResponseBodyLimit(): HttpResponseBodyLimit
    {
        return $this->httpSettings->responseBodyLimit();
    }

    /** Returns the configured HTTP request body limit as a typed value. */
    public function httpRequestBodyLimit(): HttpRequestBodyLimit
    {
        return $this->httpSettings->requestBodyLimit();
    }

    /** Returns the configured retry attempt and delay policy. */
    public function retryPolicy(): RetryPolicy
    {
        return $this->retryPolicy;
    }

    /** Whether retries are enabled for requests that declare retry safety. */
    public function retryEnabled(): bool
    {
        return $this->retryEnabled;
    }

    /** Returns the provider circuit-breaker thresholds and timings. */
    public function circuitBreakerPolicy(): CircuitBreakerPolicy
    {
        return $this->circuitBreakerPolicy;
    }

    /** Whether the circuit-breaker layer is enabled. */
    public function circuitBreakerEnabled(): bool
    {
        return $this->circuitBreakerEnabled;
    }

    /** Whether the shared 429 cooldown (backpressure) layer is enabled. */
    public function backpressureEnabled(): bool
    {
        return $this->backpressureEnabled;
    }

    /** Whether requests are serialized per rate-limit scope; false whenever backpressure is disabled. */
    public function executionLockEnabled(): bool
    {
        return $this->executionLockEnabled;
    }

    /** Returns the fallback cooldown for 429 responses without a valid Retry-After value, in seconds. */
    public function rateLimitFallbackCooldownSeconds(): int
    {
        return $this->rateLimitFallbackCooldownSeconds;
    }

    /** Returns the maximum runtime of one local Schematron process, in seconds. */
    public function schematronProcessTimeoutSeconds(): int
    {
        return $this->localValidationSettings->processTimeoutSeconds();
    }

    /** Returns the maximum number of simultaneous local Schematron validations. */
    public function schematronMaxConcurrentValidations(): int
    {
        return $this->localValidationSettings->maxConcurrentValidations();
    }

    /** Returns the maximum number of findings retained by each XSD or Schematron validator. */
    public function validationMaxIssues(): int
    {
        return $this->localValidationSettings->maxIssues();
    }

    /** Returns the maximum accepted Schematron report size in bytes. */
    public function schematronMaxReportBytes(): int
    {
        return $this->localValidationSettings->maxReportBytes();
    }

    /** Returns the maximum captured Schematron process error output in bytes. */
    public function schematronMaxErrorOutputBytes(): int
    {
        return $this->localValidationSettings->maxErrorOutputBytes();
    }

    /** Returns the maximum characters retained for one Schematron process error detail. */
    public function schematronMaxErrorDetailCharacters(): int
    {
        return $this->localValidationSettings->maxErrorDetailCharacters();
    }

    /** Returns the Java heap limit for each Schematron process in megabytes, or null to use the JVM default. */
    public function schematronJavaMaxHeapMegabytes(): ?int
    {
        return $this->localValidationSettings->javaMaxHeapMegabytes();
    }

    /**
     * Reads runtime configuration sections in validation order.
     *
     * @param array<array-key, mixed> $configuration Full package configuration array.
     * @return array{
     *     runtime: array<array-key, mixed>,
     *     http: array<array-key, mixed>,
     *     retry: array<array-key, mixed>,
     *     circuit_breaker: array<array-key, mixed>,
     *     backpressure: array<array-key, mixed>,
     *     schematron: array<array-key, mixed>,
     *     validation: array<array-key, mixed>
     * }
     */
    private static function readRuntimeSections(array $configuration): array
    {
        $runtime = ConfigurationValues::section($configuration, 'runtime', 'runtime');

        return [
            'runtime'         => $runtime,
            'http'            => ConfigurationValues::section($runtime, 'http', 'runtime.http'),
            'retry'           => ConfigurationValues::section($runtime, 'retry', 'runtime.retry'),
            'circuit_breaker' => ConfigurationValues::section($runtime, 'circuit_breaker', 'runtime.circuit_breaker'),
            'backpressure'    => ConfigurationValues::section($runtime, 'backpressure', 'runtime.backpressure'),
            'schematron'      => ConfigurationValues::section($runtime, 'schematron', 'runtime.schematron'),
            'validation'      => ConfigurationValues::section($runtime, 'validation', 'runtime.validation'),
        ];
    }

    /**
     * Rejects keys outside the supported runtime configuration schema.
     *
     * @param array{
     *     runtime: array<array-key, mixed>,
     *     http: array<array-key, mixed>,
     *     retry: array<array-key, mixed>,
     *     circuit_breaker: array<array-key, mixed>,
     *     backpressure: array<array-key, mixed>,
     *     schematron: array<array-key, mixed>,
     *     validation: array<array-key, mixed>
     * } $sections Runtime configuration sections.
     */
    private static function assertRuntimeSectionKeys(array $sections): void
    {
        ConfigurationValues::assertAllowedKeys(
            $sections['runtime'],
            ['http', 'retry', 'circuit_breaker', 'backpressure', 'validation', 'schematron'],
            'runtime'
        );
        ConfigurationValues::assertAllowedKeys(
            $sections['http'],
            ['connect_timeout_seconds', 'request_timeout_seconds', 'response_max_bytes', 'request_max_bytes'],
            'runtime.http'
        );
        ConfigurationValues::assertAllowedKeys(
            $sections['retry'],
            ['enabled', 'max_attempts', 'base_delay_ms', 'max_delay_ms', 'max_inline_delay_ms'],
            'runtime.retry'
        );
        ConfigurationValues::assertAllowedKeys(
            $sections['circuit_breaker'],
            ['enabled', 'failure_threshold', 'failure_window_seconds', 'open_seconds'],
            'runtime.circuit_breaker'
        );
        ConfigurationValues::assertAllowedKeys(
            $sections['backpressure'],
            ['enabled', 'execution_lock', 'fallback_cooldown_seconds'],
            'runtime.backpressure'
        );
        ConfigurationValues::assertAllowedKeys($sections['validation'], ['max_issues'], 'runtime.validation');
        ConfigurationValues::assertAllowedKeys(
            $sections['schematron'],
            [
                'max_concurrent_validations',
                'process_timeout_seconds',
                'max_report_bytes',
                'max_error_output_bytes',
                'max_error_detail_characters',
                'java_max_heap_mb',
            ],
            'runtime.schematron'
        );
    }

    /**
     * Builds the runtime configuration from already parsed and checked sections.
     *
     * @param array{
     *     runtime: array<array-key, mixed>,
     *     http: array<array-key, mixed>,
     *     retry: array<array-key, mixed>,
     *     circuit_breaker: array<array-key, mixed>,
     *     backpressure: array<array-key, mixed>,
     *     schematron: array<array-key, mixed>,
     *     validation: array<array-key, mixed>
     * } $sections Runtime configuration sections.
     */
    private static function createFromRuntimeSections(array $sections): self
    {
        $retry          = $sections['retry'];
        $circuitBreaker = $sections['circuit_breaker'];
        $backpressure   = $sections['backpressure'];

        return new self(
            self::createHttpSettings($sections['http']),
            self::createRetryPolicy($retry),
            ConfigurationValues::boolean($retry['enabled'] ?? true, 'runtime.retry.enabled'),
            self::createCircuitBreakerPolicy($circuitBreaker),
            ConfigurationValues::boolean($circuitBreaker['enabled'] ?? true, 'runtime.circuit_breaker.enabled'),
            ConfigurationValues::boolean($backpressure['enabled'] ?? true, 'runtime.backpressure.enabled'),
            ConfigurationValues::boolean(
                $backpressure['execution_lock'] ?? true,
                'runtime.backpressure.execution_lock'
            ),
            ConfigurationValues::integer(
                $backpressure,
                'fallback_cooldown_seconds',
                self::DEFAULT_HTTP_RATE_LIMIT_COOLDOWN_SECONDS,
                'runtime.backpressure.fallback_cooldown_seconds'
            ),
            self::createLocalValidationSettings($sections['schematron'], $sections['validation'])
        );
    }

    /**
     * Builds HTTP limits from `runtime.http`, using defaults for omitted values.
     *
     * @param  array<string, mixed> $configuration HTTP runtime subsection.
     * @return HttpRuntimeSettings
     */
    private static function createHttpSettings(array $configuration): HttpRuntimeSettings
    {
        return new HttpRuntimeSettings(
            ConfigurationValues::integer(
                $configuration,
                'connect_timeout_seconds',
                self::DEFAULT_HTTP_CONNECT_TIMEOUT_SECONDS,
                'runtime.http.connect_timeout_seconds'
            ),
            ConfigurationValues::integer(
                $configuration,
                'request_timeout_seconds',
                self::DEFAULT_HTTP_REQUEST_TIMEOUT_SECONDS,
                'runtime.http.request_timeout_seconds'
            ),
            HttpResponseBodyLimit::fromBytes(ConfigurationValues::integer(
                $configuration,
                'response_max_bytes',
                self::DEFAULT_HTTP_RESPONSE_MAX_BYTES,
                'runtime.http.response_max_bytes'
            )),
            HttpRequestBodyLimit::fromBytes(ConfigurationValues::integer(
                $configuration,
                'request_max_bytes',
                self::DEFAULT_HTTP_REQUEST_MAX_BYTES,
                'runtime.http.request_max_bytes'
            ))
        );
    }

    /**
     * Builds local validation limits from `runtime.schematron` and `runtime.validation`.
     *
     * @param  array<string, mixed>           $schematron Schematron runtime subsection.
     * @param  array<string, mixed>           $validation Validation runtime subsection.
     * @return LocalValidationRuntimeSettings
     */
    private static function createLocalValidationSettings(
        array $schematron,
        array $validation
    ): LocalValidationRuntimeSettings {
        return new LocalValidationRuntimeSettings(
            ConfigurationValues::integer(
                $schematron,
                'process_timeout_seconds',
                self::DEFAULT_SCHEMATRON_PROCESS_TIMEOUT_SECONDS,
                'runtime.schematron.process_timeout_seconds'
            ),
            ConfigurationValues::integer(
                $schematron,
                'max_concurrent_validations',
                self::DEFAULT_SCHEMATRON_MAX_CONCURRENT_VALIDATIONS,
                'runtime.schematron.max_concurrent_validations'
            ),
            ConfigurationValues::integer(
                $validation,
                'max_issues',
                self::DEFAULT_VALIDATION_MAX_ISSUES,
                'runtime.validation.max_issues'
            ),
            ConfigurationValues::integer(
                $schematron,
                'max_report_bytes',
                self::DEFAULT_SCHEMATRON_MAX_REPORT_BYTES,
                'runtime.schematron.max_report_bytes'
            ),
            ConfigurationValues::integer(
                $schematron,
                'max_error_output_bytes',
                self::DEFAULT_SCHEMATRON_MAX_ERROR_OUTPUT_BYTES,
                'runtime.schematron.max_error_output_bytes'
            ),
            ConfigurationValues::integer(
                $schematron,
                'max_error_detail_characters',
                self::DEFAULT_SCHEMATRON_MAX_ERROR_DETAIL_CHARACTERS,
                'runtime.schematron.max_error_detail_characters'
            ),
            self::optionalHeapLimit($schematron)
        );
    }

    /**
     * Reads the optional Java heap limit; an absent, null or empty value means the JVM default.
     *
     * @param  array<string, mixed> $schematron Schematron runtime subsection.
     * @return int|null
     */
    private static function optionalHeapLimit(array $schematron): ?int
    {
        $value = $schematron['java_max_heap_mb'] ?? null;

        if ($value === null || (is_string($value) && trim($value) === '')) {
            return null;
        }

        return ConfigurationValues::integer(
            $schematron,
            'java_max_heap_mb',
            self::MIN_CONFIGURED_SCHEMATRON_JAVA_HEAP_MB,
            'runtime.schematron.java_max_heap_mb'
        );
    }

    /**
     * Builds the retry policy from `runtime.retry`, inheriting policy defaults where omitted.
     *
     * @param  array<string, mixed> $configuration Retry runtime subsection.
     * @return RetryPolicy
     */
    private static function createRetryPolicy(array $configuration): RetryPolicy
    {
        $defaults = RetryPolicy::default();

        return new RetryPolicy(
            ConfigurationValues::integer(
                $configuration,
                'max_attempts',
                $defaults->maxAttempts(),
                'runtime.retry.max_attempts'
            ),
            ConfigurationValues::integer(
                $configuration,
                'base_delay_ms',
                $defaults->delayForRetry(1),
                'runtime.retry.base_delay_ms'
            ),
            ConfigurationValues::integer(
                $configuration,
                'max_delay_ms',
                $defaults->maxDelayMs(),
                'runtime.retry.max_delay_ms'
            ),
            ConfigurationValues::integer(
                $configuration,
                'max_inline_delay_ms',
                $defaults->maxInlineDelayMs(),
                'runtime.retry.max_inline_delay_ms'
            )
        );
    }

    /**
     * Builds the circuit-breaker policy from `runtime.circuit_breaker`.
     *
     * @param  array<string, mixed> $configuration Circuit-breaker runtime subsection.
     * @return CircuitBreakerPolicy
     */
    private static function createCircuitBreakerPolicy(array $configuration): CircuitBreakerPolicy
    {
        $defaults = new CircuitBreakerPolicy();

        return new CircuitBreakerPolicy(
            ConfigurationValues::integer(
                $configuration,
                'failure_threshold',
                $defaults->failureThreshold(),
                'runtime.circuit_breaker.failure_threshold'
            ),
            ConfigurationValues::integer(
                $configuration,
                'failure_window_seconds',
                $defaults->failureWindowSeconds(),
                'runtime.circuit_breaker.failure_window_seconds'
            ),
            ConfigurationValues::integer(
                $configuration,
                'open_seconds',
                $defaults->openDurationSeconds(),
                'runtime.circuit_breaker.open_seconds'
            )
        );
    }

    /**
     * Validates a positive runtime limit.
     *
     * @param  int                    $value   Configured limit.
     * @param  string                 $setting Setting label used in the exception message.
     * @throws ConfigurationException when the value is less than one
     */
    private static function assertPositive(int $value, string $setting): void
    {
        if ($value < 1) {
            throw new ConfigurationException($setting . ' must be greater than zero.');
        }
    }

    /**
     * Keeps configurable output caps within absolute process-safety limits.
     *
     * @param  int                    $value   Configured output or issue limit.
     * @param  int                    $maximum Maximum supported value.
     * @param  string                 $setting Setting label used in the exception message.
     * @throws ConfigurationException when the value exceeds its maximum
     */
    private static function assertAtMost(int $value, int $maximum, string $setting): void
    {
        if ($value > $maximum) {
            throw new ConfigurationException($setting . ' must not exceed ' . $maximum . '.');
        }
    }
}
