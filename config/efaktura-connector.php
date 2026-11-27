<?php

/**
 * Package defaults for provider connection, runtime policy and Laravel service wiring.
 *
 * The `clients` profiles are parsed by SapiClientsConfiguration, Integrator API connection values by
 * IntegratorAccountConfiguration, `runtime` by ConnectorRuntimeConfiguration, and `laravel` by
 * LaravelServiceConfiguration and the service provider. The optional `integrator_api.pds` section is
 * parsed separately by PdsSigningConfiguration, not as part of the Integrator API connection values.
 * Environment variables provide deployment defaults; a published Laravel config file can override
 * these values directly. Time and size units are stated in each setting name.
 *
 * @return array{
 *     default_client: string,
 *     clients: array<string, array{
 *         base_url?: string,
 *         client_id?: string,
 *         client_secret?: string,
 *         client_scope?: string
 *     }>,
 *     integrator_api: array{
 *         base_url: string,
 *         integrator_id: string,
 *         token: string,
 *         pds: array{signing_secret: string}
 *     },
 *     runtime: array{
 *         http: array{
 *             connect_timeout_seconds: int|string,
 *             request_timeout_seconds: int|string,
 *             response_max_bytes: int|string,
 *             request_max_bytes: int|string
 *         },
 *         retry: array{
 *             enabled: bool|int|string,
 *             max_attempts: int|string,
 *             base_delay_ms: int|string,
 *             max_delay_ms: int|string,
 *             max_inline_delay_ms: int|string
 *         },
 *         circuit_breaker: array{
 *             enabled: bool|int|string,
 *             failure_threshold: int|string,
 *             failure_window_seconds: int|string,
 *             open_seconds: int|string
 *         },
 *         backpressure: array{
 *             enabled: bool|int|string,
 *             execution_lock: bool|int|string,
 *             fallback_cooldown_seconds: int|string
 *         },
 *         validation: array{max_issues: int|string},
 *         schematron: array{
 *             max_concurrent_validations: int|string,
 *             process_timeout_seconds: int|string,
 *             max_report_bytes: int|string,
 *             max_error_output_bytes: int|string,
 *             max_error_detail_characters: int|string,
 *             java_max_heap_mb: int|string|null
 *         }
 *     },
 *     laravel: array{
 *         cache_store: string|null,
 *         request_execution: array{
 *             executor: class-string<
 *                 HttpRequestExecutorInterface
 *             >,
 *             logging: bool|int|string,
 *             metrics: bool|int|string,
 *             execution_lock: array{
 *                 driver: 'cache'|'file',
 *                 store: string|null,
 *                 lease_seconds: int|string|null,
 *                 wait_seconds: int|string,
 *                 temporary_directory: string|null
 *             }
 *         },
 *         validation: array{
 *             stages: list<class-string<
 *                 UblValidationStageInterface
 *             >>,
 *             resource_directory: string|null,
 *             working_directory: string|null,
 *             concurrency_lock_directory: string|null,
 *             java_binary: string|null
 *         },
 *         services: array<class-string, class-string>
 *     }
 * }
 */

use DreaM1\EfakturaConnector\Core\CircuitBreaker\CircuitBreakerDefaults;
use DreaM1\EfakturaConnector\Core\Configuration\ConnectorRuntimeConfiguration;
use DreaM1\EfakturaConnector\Core\Configuration\SapiClientsConfiguration;
use DreaM1\EfakturaConnector\Core\Retry\RetryPolicy;
use DreaM1\EfakturaConnector\Core\Ubl\Contracts\UblValidationStageInterface;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Transport\Execution\HttpRequestExecutorInterface;
use DreaM1\EfakturaConnector\Laravel\Configuration\LaravelServiceConfiguration;

return [
    /** Alias used by the package's direct Laravel service bindings. */
    'default_client' => env('EFAKTURA_DEFAULT_CLIENT', SapiClientsConfiguration::DEFAULT_CLIENT_NAME),

    /**
     * Named OAuth credentials. Each profile names its own provider URL and gets an isolated token
     * provider; empty credentials allow the host application to boot before provider access is configured.
     */
    'clients' => [
        SapiClientsConfiguration::DEFAULT_CLIENT_NAME => [
            /** Provider API URL of this profile, for example the server root without `/sapi` or `/v1`. */
            'base_url'      => env('EFAKTURA_BASE_URL', ''),
            'client_id'     => env('EFAKTURA_CLIENT_ID', ''),
            'client_secret' => env('EFAKTURA_CLIENT_SECRET', ''),
            /** Explicit provider-issued OAuth scopes; an empty value is rejected by assertReady(). */
            'client_scope' => env(
                'EFAKTURA_CLIENT_SCOPE',
                ''
            ),
        ],
    ],

    /** Credentials for the separate DataHosting Integrator API. */
    'integrator_api' => [
        /** Defaults to the EFAKTURA_BASE_URL environment value when no Integrator-specific URL is set. */
        'base_url'      => env('EFAKTURA_INTEGRATOR_BASE_URL', env('EFAKTURA_BASE_URL', '')),
        'integrator_id' => env('EFAKTURA_INTEGRATOR_ID', ''),
        /** Keep this secret in the environment or a secret store. */
        'token' => env('EFAKTURA_INTEGRATOR_API_TOKEN', ''),
        /** Separate PDS request signing secret; this is not the Integrator API token. */
        'pds' => [
            'signing_secret' => env('EFAKTURA_PDS_SIGNING_SECRET', ''),
        ],
    ],

    /**
     * Framework-independent HTTP, retry, rate-limit and local validation policies.
     * These settings are parsed into ConnectorRuntimeConfiguration and are not Laravel bindings.
     */
    'runtime' => [
        /** HTTP timeouts and accepted response body size, and encoded body size of the Gateway batch send (`POST /v1/send-documents`). */
        'http' => [
            'connect_timeout_seconds' => env(
                'EFAKTURA_HTTP_CONNECT_TIMEOUT_SECONDS',
                ConnectorRuntimeConfiguration::DEFAULT_HTTP_CONNECT_TIMEOUT_SECONDS
            ),
            'request_timeout_seconds' => env(
                'EFAKTURA_HTTP_REQUEST_TIMEOUT_SECONDS',
                ConnectorRuntimeConfiguration::DEFAULT_HTTP_REQUEST_TIMEOUT_SECONDS
            ),
            'response_max_bytes' => env(
                'EFAKTURA_HTTP_RESPONSE_MAX_BYTES',
                ConnectorRuntimeConfiguration::DEFAULT_HTTP_RESPONSE_MAX_BYTES
            ),
            'request_max_bytes' => env(
                'EFAKTURA_HTTP_REQUEST_MAX_BYTES',
                ConnectorRuntimeConfiguration::DEFAULT_HTTP_REQUEST_MAX_BYTES
            ),
        ],
        /** Retry policy for requests whose ApiRequest explicitly permits retry. */
        'retry' => [
            'enabled'             => env('EFAKTURA_HTTP_RETRY_ENABLED', true),
            'max_attempts'        => env('EFAKTURA_HTTP_RETRY_MAX_ATTEMPTS', RetryPolicy::DEFAULT_MAX_ATTEMPTS),
            'base_delay_ms'       => env('EFAKTURA_HTTP_RETRY_BASE_DELAY_MS', RetryPolicy::DEFAULT_BASE_DELAY_MS),
            'max_delay_ms'        => env('EFAKTURA_HTTP_RETRY_MAX_DELAY_MS', RetryPolicy::DEFAULT_MAX_DELAY_MS),
            'max_inline_delay_ms' => env(
                'EFAKTURA_HTTP_RETRY_MAX_INLINE_DELAY_MS',
                RetryPolicy::DEFAULT_MAX_INLINE_DELAY_MS
            ),
        ],
        /** Circuit breaker layer switch plus failure threshold, observation window and open duration. */
        'circuit_breaker' => [
            'enabled'           => env('EFAKTURA_CIRCUIT_BREAKER_ENABLED', true),
            'failure_threshold' => env(
                'EFAKTURA_CIRCUIT_BREAKER_FAILURE_THRESHOLD',
                CircuitBreakerDefaults::FAILURE_THRESHOLD
            ),
            'failure_window_seconds' => env(
                'EFAKTURA_CIRCUIT_BREAKER_FAILURE_WINDOW_SECONDS',
                CircuitBreakerDefaults::FAILURE_WINDOW_SECONDS
            ),
            'open_seconds' => env(
                'EFAKTURA_CIRCUIT_BREAKER_OPEN_SECONDS',
                CircuitBreakerDefaults::OPEN_DURATION_SECONDS
            ),
        ],
        /**
         * Shared cooldown after HTTP 429. `execution_lock` additionally serializes requests per rate-limit
         * scope and has an effect only while backpressure is enabled. The fallback is the wait after a 429
         * response that has no usable Retry-After value.
         */
        'backpressure' => [
            'enabled'                   => env('EFAKTURA_HTTP_BACKPRESSURE_ENABLED', true),
            'execution_lock'            => env('EFAKTURA_HTTP_EXECUTION_LOCK_ENABLED', true),
            'fallback_cooldown_seconds' => env(
                'EFAKTURA_HTTP_RATE_LIMIT_FALLBACK_COOLDOWN_SECONDS',
                ConnectorRuntimeConfiguration::DEFAULT_HTTP_RATE_LIMIT_COOLDOWN_SECONDS
            ),
        ],
        /** Maximum findings retained per local validation stage; additional findings are counted as omitted. */
        'validation' => [
            'max_issues' => env(
                'EFAKTURA_VALIDATION_MAX_ISSUES',
                ConnectorRuntimeConfiguration::DEFAULT_VALIDATION_MAX_ISSUES
            ),
        ],
        /** Time, concurrency and output limits for local Peppol Schematron execution. */
        'schematron' => [
            'max_concurrent_validations' => env(
                'EFAKTURA_SCHEMATRON_MAX_CONCURRENT_VALIDATIONS',
                ConnectorRuntimeConfiguration::DEFAULT_SCHEMATRON_MAX_CONCURRENT_VALIDATIONS
            ),
            'process_timeout_seconds' => env(
                'EFAKTURA_SCHEMATRON_PROCESS_TIMEOUT_SECONDS',
                ConnectorRuntimeConfiguration::DEFAULT_SCHEMATRON_PROCESS_TIMEOUT_SECONDS
            ),
            'max_report_bytes' => env(
                'EFAKTURA_SCHEMATRON_MAX_REPORT_BYTES',
                ConnectorRuntimeConfiguration::DEFAULT_SCHEMATRON_MAX_REPORT_BYTES
            ),
            'max_error_output_bytes' => env(
                'EFAKTURA_SCHEMATRON_MAX_ERROR_OUTPUT_BYTES',
                ConnectorRuntimeConfiguration::DEFAULT_SCHEMATRON_MAX_ERROR_OUTPUT_BYTES
            ),
            'max_error_detail_characters' => env(
                'EFAKTURA_SCHEMATRON_MAX_ERROR_DETAIL_CHARACTERS',
                ConnectorRuntimeConfiguration::DEFAULT_SCHEMATRON_MAX_ERROR_DETAIL_CHARACTERS
            ),
            /** Optional heap limit (-Xmx) of each Java process in megabytes, 64 to 65536; null keeps the JVM default. */
            'java_max_heap_mb' => env('EFAKTURA_SCHEMATRON_JAVA_MAX_HEAP_MB'),
        ],
    ],

    /**
     * Laravel-specific executor, validation and service-container choices.
     * Framework-independent policy values belong in `runtime` above.
     */
    'laravel' => [
        /** Shared store for token, breaker, cooldown and lock state; null uses Laravel's default. */
        'cache_store' => env('EFAKTURA_CACHE_STORE'),
        /** Base HTTP executor, optional decorator layers and request-lock coordination. */
        'request_execution' => [
            'executor' => LaravelServiceConfiguration::DEFAULT_REQUEST_EXECUTOR_CLASS,
            /** Attach the default Laravel HTTP logger; the other layer switches live in `runtime`. */
            'logging' => env('EFAKTURA_HTTP_LOGGING_ENABLED', true),
            /** Attach HTTP metrics when the host binds ConnectorMetricsInterface. */
            'metrics' => env('EFAKTURA_HTTP_METRICS_ENABLED', true),
            /** Atomic lock backend; store optionally overrides the shared laravel.cache_store. */
            'execution_lock' => [
                'driver' => env(
                    'EFAKTURA_HTTP_EXECUTION_LOCK_DRIVER',
                    LaravelServiceConfiguration::DEFAULT_EXECUTION_LOCK_DRIVER
                ),
                'store' => env('EFAKTURA_HTTP_EXECUTION_LOCK_STORE'),
                /**
                 * Cache-lock lease in seconds. Null derives it as the request timeout plus a 5 second margin;
                 * an explicit value must not be shorter. Retries run unlocked, so retry settings do not matter.
                 */
                'lease_seconds' => env('EFAKTURA_HTTP_EXECUTION_LOCK_LEASE_SECONDS'),
                /** Maximum wait before a competing request fails with RequestCoordinationException. */
                'wait_seconds' => env(
                    'EFAKTURA_HTTP_EXECUTION_LOCK_WAIT_SECONDS',
                    LaravelServiceConfiguration::DEFAULT_EXECUTION_LOCK_WAIT_SECONDS
                ),
                /** Optional directory used only when driver is `file`. */
                'temporary_directory' => env('EFAKTURA_HTTP_EXECUTION_LOCK_DIRECTORY'),
            ],
        ],
        /** Local validation stages and paths used by the Schematron process. */
        'validation' => [
            'stages' => LaravelServiceConfiguration::DEFAULT_VALIDATION_STAGE_CLASSES,
            /** Null uses bundled validation resources. */
            'resource_directory' => env('EFAKTURA_VALIDATION_RESOURCE_DIRECTORY'),
            /** Null uses the Laravel application base path as the validation working directory. */
            'working_directory' => env('EFAKTURA_VALIDATION_WORKING_DIRECTORY'),
            /** Optional directory for file-backed Schematron concurrency locks. */
            'concurrency_lock_directory' => env('EFAKTURA_SCHEMATRON_LOCK_DIRECTORY'),
            /** Null prefers JAVA_HOME, then searches PATH for the Java executable. */
            'java_binary' => env('EFAKTURA_JAVA_BINARY'),
        ],
        /** Map service contract class names to compatible host implementation class names. */
        'services' => [
            // \DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Transport\Coordination\HttpRequestBackpressureInterface::class
            //     => App\Infrastructure\CustomHttpRequestBackpressure::class,
        ],
    ],
];
