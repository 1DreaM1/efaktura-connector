<?php

namespace DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Transport\Execution;

use DreaM1\EfakturaConnector\Core\CircuitBreaker\InMemoryCircuitBreaker;
use DreaM1\EfakturaConnector\Core\Configuration\HttpRuntimeConfigurationInterface;
use DreaM1\EfakturaConnector\Core\Retry\RetryPolicy;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Transport\Coordination\HttpRequestBackpressureInterface;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Transport\Coordination\InMemoryHttpRequestBackpressure;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Clock\ClockInterface;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Clock\SystemClock;

/**
 * Builds the default cURL execution stack with configurable resilience, logging and metrics layers.
 *
 * The logger wraps the base executor inside the retry layer, so each actual network attempt is
 * observable. Metrics are recorded at the same per-attempt level. Logger failures are swallowed by
 * LoggingHttpRequestExecutor and never replace an API result. Retry safety is still determined by
 * each request; enabling the retry layer does not make unsafe operations retryable.
 */
final class DefaultHttpRequestExecutorFactory
{
    /**
     * Creates the default stack using named, immutable executor options.
     *
     * When no base executor or resilience dependency is supplied, the factory uses its standard
     * cURL and in-memory implementations.
     *
     * @param  HttpRequestExecutorOptions   $options Configuration and optional dependencies for the stack.
     * @return HttpRequestExecutorInterface
     */
    public static function create(
        HttpRequestExecutorOptions $options
    ): HttpRequestExecutorInterface {
        $clock = $options->clock() ?? new SystemClock();

        $backpressureStore = $options->backpressureEnabled()
            ? ($options->backpressure() ?? new InMemoryHttpRequestBackpressure(static function () use ($clock): int {
                return $clock->now();
            }))
            : null;

        // Build outward from the network: metrics/logging, the cooldown check and its lock all sit below retries,
        // so every attempt is observed, re-checks the shared cooldown and holds the lock only while it runs;
        // the circuit breaker then wraps the whole retrying operation.
        $executor = $options->baseExecutor() ?? new CurlRequestExecutor();
        $executor = self::observeAttempts($executor, $options);
        $executor = self::withBackpressure($executor, $backpressureStore, $options, $clock);
        $executor = self::withRetries($executor, $backpressureStore, $options, $clock);
        $executor = self::withCircuitBreaker($executor, $options);

        // Report requests rejected before any HTTP attempt; the inner layers see only real attempts.
        return self::observeRejections($executor, $backpressureStore !== null, $options);
    }

    /**
     * Builds the default stack from runtime settings and typed executor options.
     * Runtime retry policy, the enabled state of the retry, circuit-breaker, backpressure and execution-lock
     * layers, cooldown and default cURL limits come from configuration.
     * A supplied base executor is preserved; otherwise the factory creates a cURL executor using
     * the configured timeouts and response-size limit. A supplied circuit breaker is preserved;
     * otherwise an in-memory breaker is created with the configured policy when that layer is enabled.
     * Runtime layer switches, retry and cooldown settings always replace the corresponding values in the options.
     *
     * @param  HttpRuntimeConfigurationInterface $configuration Runtime settings for the stack.
     * @param  HttpRequestExecutorOptions        $options       Optional stack dependencies and resilience layers.
     * @return HttpRequestExecutorInterface
     */
    public static function createFromRuntimeConfiguration(
        HttpRuntimeConfigurationInterface $configuration,
        HttpRequestExecutorOptions $options
    ): HttpRequestExecutorInterface {
        $baseExecutor = $options->baseExecutor();

        if ($baseExecutor === null) {
            $baseExecutor = new CurlRequestExecutor(
                $configuration->httpConnectTimeoutSeconds(),
                $configuration->httpRequestTimeoutSeconds(),
                $configuration->httpResponseBodyLimit()
            );
        }

        $options = $options
            ->withCircuitBreakerEnabled($configuration->circuitBreakerEnabled())
            ->withBackpressureEnabled($configuration->backpressureEnabled())
            ->withExecutionLockEnabled($configuration->executionLockEnabled());

        if ($options->circuitBreakerEnabled()) {
            $circuitBreaker = $options->circuitBreaker() ?? new InMemoryCircuitBreaker(
                null,
                $configuration->circuitBreakerPolicy()
            );

            $options = $options->withCircuitBreaker($circuitBreaker);
        }

        $options = $options
            ->withBaseExecutor($baseExecutor)
            ->withRetryPolicy($configuration->retryPolicy())
            ->withRetryEnabled($configuration->retryEnabled())
            ->withRateLimitFallbackCooldownSeconds($configuration->rateLimitFallbackCooldownSeconds());

        return self::create($options);
    }

    /** Wraps the executor so every real attempt is counted and logged, when metrics or a logger are configured. */
    private static function observeAttempts(
        HttpRequestExecutorInterface $executor,
        HttpRequestExecutorOptions $options
    ): HttpRequestExecutorInterface {
        if ($options->metrics() !== null) {
            $executor = new MetricsHttpRequestExecutor($executor, $options->metrics());
        }

        if ($options->logger() !== null) {
            $executor = new LoggingHttpRequestExecutor($executor, $options->logger());
        }

        return $executor;
    }

    /** Adds 429 recording and the cooldown check with its execution lock, when backpressure is enabled. */
    private static function withBackpressure(
        HttpRequestExecutorInterface $executor,
        ?HttpRequestBackpressureInterface $backpressureStore,
        HttpRequestExecutorOptions $options,
        ClockInterface $clock
    ): HttpRequestExecutorInterface {
        if ($backpressureStore === null) {
            return $executor;
        }

        // Record every 429 attempt before the retry layer evaluates that response.
        $executor = new RateLimitRecordingHttpRequestExecutor(
            $executor,
            $backpressureStore,
            $options->rateLimitFallbackCooldownSeconds(),
            $clock
        );

        // Check the cooldown and serialize each attempt; the lock is released before any retry delay.
        return new BackpressureHttpRequestExecutor(
            $executor,
            $backpressureStore,
            $options->executionLock(),
            $options->executionLockEnabled(),
            $clock
        );
    }

    /** Adds the retry layer above the backpressure layers, when retries are enabled. */
    private static function withRetries(
        HttpRequestExecutorInterface $executor,
        ?HttpRequestBackpressureInterface $backpressureStore,
        HttpRequestExecutorOptions $options,
        ClockInterface $clock
    ): HttpRequestExecutorInterface {
        if (! $options->retryEnabled()) {
            return $executor;
        }

        return new RetryingHttpRequestExecutor(
            $executor,
            $options->retryPolicy() ?? RetryPolicy::default(),
            $options->sleepFn(),
            null,
            $backpressureStore,
            $clock
        );
    }

    /** Wraps the whole retrying operation in the circuit breaker, when it is enabled. */
    private static function withCircuitBreaker(
        HttpRequestExecutorInterface $executor,
        HttpRequestExecutorOptions $options
    ): HttpRequestExecutorInterface {
        if (! $options->circuitBreakerEnabled()) {
            return $executor;
        }

        return new CircuitBreakerHttpRequestExecutor(
            $executor,
            $options->circuitBreaker() ?? new InMemoryCircuitBreaker()
        );
    }

    /**
     * Reports requests rejected before any HTTP attempt by the cooldown or the circuit breaker.
     *
     * Only needed when one of them can reject and a logger or metrics are configured.
     */
    private static function observeRejections(
        HttpRequestExecutorInterface $executor,
        bool $hasBackpressure,
        HttpRequestExecutorOptions $options
    ): HttpRequestExecutorInterface {
        $canReject = $hasBackpressure || $options->circuitBreakerEnabled();

        if (! $canReject || ($options->logger() === null && $options->metrics() === null)) {
            return $executor;
        }

        return new RejectionObservingHttpRequestExecutor($executor, $options->logger(), $options->metrics());
    }
}
