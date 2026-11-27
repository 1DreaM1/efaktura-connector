<?php

namespace DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Transport\Execution;

use DreaM1\EfakturaConnector\Core\CircuitBreaker\CircuitBreakerInterface;
use DreaM1\EfakturaConnector\Core\Configuration\ConnectorRuntimeConfiguration;
use DreaM1\EfakturaConnector\Core\Retry\RetryPolicy;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Transport\Coordination\HttpRequestBackpressureInterface;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Transport\Coordination\ScopedHttpRequestExecutionLockInterface;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Clock\ClockInterface;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Metrics\ConnectorMetricsInterface;

/**
 * Immutable options for composing an HTTP request executor stack.
 *
 * Each `with*()` method returns a clone, allowing the caller to build a configuration chain without
 * mutating the original. Resilience layers are enabled by default; logger, metrics, concrete
 * dependencies and retry policy are optional and can be supplied by the host or the factory.
 *
 * @api
 */
final class HttpRequestExecutorOptions
{
    private ?HttpRequestExecutionLoggerInterface $logger = null;
    private ?HttpRequestExecutorInterface $baseExecutor  = null;
    /** @var (callable(int): void)|null Retry delay callback receiving milliseconds. */
    private $sleepFn                                                = null;
    private ?CircuitBreakerInterface $circuitBreaker                = null;
    private ?HttpRequestBackpressureInterface $backpressure         = null;
    private ?ConnectorMetricsInterface $metrics                     = null;
    private ?RetryPolicy $retryPolicy                               = null;
    private ?ScopedHttpRequestExecutionLockInterface $executionLock = null;
    private ?ClockInterface $clock                                  = null;
    private bool $retryEnabled                                      = true;
    private bool $circuitBreakerEnabled                             = true;
    private bool $backpressureEnabled                               = true;
    private bool $executionLockEnabled                              = true;
    private int $rateLimitFallbackCooldownSeconds                   = ConnectorRuntimeConfiguration::DEFAULT_HTTP_RATE_LIMIT_COOLDOWN_SECONDS;

    private function __construct()
    {
    }

    /**
     * Returns a new set of options with the factory defaults.
     *
     * Retry, circuit-breaker, provider cooldown and execution-lock layers are enabled. Dependencies
     * are left unset so the factory can provide defaults or the host can inject shared services.
     */
    public static function defaults(): self
    {
        return new self();
    }

    /**
     * Returns a copy with a request execution logger.
     *
     * @param HttpRequestExecutionLoggerInterface|null $logger Logger to use, or null to omit logging.
     */
    public function withLogger(?HttpRequestExecutionLoggerInterface $logger): self
    {
        $options         = clone $this;
        $options->logger = $logger;

        return $options;
    }

    /**
     * Returns a copy with the executor wrapped by the configured layers.
     *
     * @param HttpRequestExecutorInterface|null $executor Base executor, or null for the factory default.
     */
    public function withBaseExecutor(?HttpRequestExecutorInterface $executor): self
    {
        $options               = clone $this;
        $options->baseExecutor = $executor;

        return $options;
    }

    /**
     * Returns a copy with the retry delay callback.
     *
     * @param (callable(int): void)|null $sleepFn Callback receiving a retry delay in milliseconds.
     */
    public function withSleepFn(?callable $sleepFn): self
    {
        $options          = clone $this;
        $options->sleepFn = $sleepFn;

        return $options;
    }

    /**
     * Returns a copy with a circuit breaker implementation.
     *
     * @param CircuitBreakerInterface|null $circuitBreaker Shared breaker to use, or null for a factory default.
     */
    public function withCircuitBreaker(?CircuitBreakerInterface $circuitBreaker): self
    {
        $options                 = clone $this;
        $options->circuitBreaker = $circuitBreaker;

        return $options;
    }

    /**
     * Returns a copy with a shared provider cooldown store.
     *
     * @param HttpRequestBackpressureInterface|null $backpressure Store for provider cooldown deadlines.
     */
    public function withBackpressure(?HttpRequestBackpressureInterface $backpressure): self
    {
        $options               = clone $this;
        $options->backpressure = $backpressure;

        return $options;
    }

    /**
     * Returns a copy with a metrics sink.
     *
     * @param ConnectorMetricsInterface|null $metrics Sink for low-cardinality connector metrics.
     */
    public function withMetrics(?ConnectorMetricsInterface $metrics): self
    {
        $options          = clone $this;
        $options->metrics = $metrics;

        return $options;
    }

    /**
     * Returns a copy with the retry policy.
     *
     * @param RetryPolicy|null $retryPolicy Retry count and delay policy.
     */
    public function withRetryPolicy(?RetryPolicy $retryPolicy): self
    {
        $options              = clone $this;
        $options->retryPolicy = $retryPolicy;

        return $options;
    }

    /**
     * Returns a copy with a lock that serializes requests by provider scope.
     *
     * @param ScopedHttpRequestExecutionLockInterface|null $executionLock Provider-scoped execution lock.
     */
    public function withExecutionLock(?ScopedHttpRequestExecutionLockInterface $executionLock): self
    {
        $options                = clone $this;
        $options->executionLock = $executionLock;

        return $options;
    }

    /**
     * Returns a copy with the time source used for retry delays and provider cooldowns.
     *
     * @param ClockInterface|null $clock Clock to use, or null for the system clock.
     */
    public function withClock(?ClockInterface $clock): self
    {
        $options        = clone $this;
        $options->clock = $clock;

        return $options;
    }

    /**
     * Returns a copy with retry execution enabled or disabled.
     *
     * @param bool $enabled Whether eligible requests may be retried.
     */
    public function withRetryEnabled(bool $enabled): self
    {
        $options               = clone $this;
        $options->retryEnabled = $enabled;

        return $options;
    }

    /**
     * Returns a copy with circuit-breaker protection enabled or disabled.
     *
     * @param bool $enabled Whether the circuit-breaker decorator is used.
     */
    public function withCircuitBreakerEnabled(bool $enabled): self
    {
        $options                        = clone $this;
        $options->circuitBreakerEnabled = $enabled;

        return $options;
    }

    /**
     * Returns a copy with provider cooldown coordination enabled or disabled.
     *
     * @param bool $enabled Whether the backpressure decorator is used.
     */
    public function withBackpressureEnabled(bool $enabled): self
    {
        $options                      = clone $this;
        $options->backpressureEnabled = $enabled;

        return $options;
    }

    /**
     * Returns a copy with cross-process request serialization enabled or disabled.
     *
     * @param bool $enabled Whether the provider-scoped lock is used by backpressure handling.
     */
    public function withExecutionLockEnabled(bool $enabled): self
    {
        $options                       = clone $this;
        $options->executionLockEnabled = $enabled;

        return $options;
    }

    /**
     * Returns a copy with the fallback cooldown for 429 responses, in seconds.
     *
     * @param int $seconds Cooldown used when Retry-After is missing or invalid.
     */
    public function withRateLimitFallbackCooldownSeconds(int $seconds): self
    {
        $options                                   = clone $this;
        $options->rateLimitFallbackCooldownSeconds = $seconds;

        return $options;
    }

    /** Returns the configured execution logger. */
    public function logger(): ?HttpRequestExecutionLoggerInterface
    {
        return $this->logger;
    }

    /** Returns the base request executor. */
    public function baseExecutor(): ?HttpRequestExecutorInterface
    {
        return $this->baseExecutor;
    }

    /**
     * Returns the callback used to wait between retries, if supplied.
     *
     * @return (callable(int): void)|null
     */
    public function sleepFn(): ?callable
    {
        return $this->sleepFn;
    }

    /** Returns the configured circuit breaker. */
    public function circuitBreaker(): ?CircuitBreakerInterface
    {
        return $this->circuitBreaker;
    }

    /** Returns the provider cooldown store. */
    public function backpressure(): ?HttpRequestBackpressureInterface
    {
        return $this->backpressure;
    }

    /** Returns the configured metrics sink. */
    public function metrics(): ?ConnectorMetricsInterface
    {
        return $this->metrics;
    }

    /** Returns the configured retry policy. */
    public function retryPolicy(): ?RetryPolicy
    {
        return $this->retryPolicy;
    }

    /** Returns the provider-scoped execution lock. */
    public function executionLock(): ?ScopedHttpRequestExecutionLockInterface
    {
        return $this->executionLock;
    }

    /** Returns the configured clock, or null when the system clock is used. */
    public function clock(): ?ClockInterface
    {
        return $this->clock;
    }

    /** Whether request retries are enabled. */
    public function retryEnabled(): bool
    {
        return $this->retryEnabled;
    }

    /** Whether circuit-breaker protection is enabled. */
    public function circuitBreakerEnabled(): bool
    {
        return $this->circuitBreakerEnabled;
    }

    /** Whether provider cooldown coordination is enabled. */
    public function backpressureEnabled(): bool
    {
        return $this->backpressureEnabled;
    }

    /** Whether cross-process request serialization is enabled. */
    public function executionLockEnabled(): bool
    {
        return $this->executionLockEnabled;
    }

    /** Returns the fallback cooldown for 429 responses, in seconds. */
    public function rateLimitFallbackCooldownSeconds(): int
    {
        return $this->rateLimitFallbackCooldownSeconds;
    }
}
