<?php

namespace DreaM1\EfakturaConnector\Core\Configuration;

use DreaM1\EfakturaConnector\Core\CircuitBreaker\CircuitBreakerPolicy;
use DreaM1\EfakturaConnector\Core\Retry\RetryPolicy;

/**
 * Framework-independent HTTP transport settings used by request execution services.
 *
 * Retry eligibility remains a property of each request; the settings only supply limits and timings.
 * Intended for consumption by host applications; implementing it in a host class is not supported and new methods may be added in minor releases.
 */
interface HttpRuntimeConfigurationInterface
{
    /** Maximum time allowed to establish an HTTP connection, in seconds. */
    public function httpConnectTimeoutSeconds(): int;

    /** Maximum total time allowed for one HTTP request, in seconds. */
    public function httpRequestTimeoutSeconds(): int;

    /** Returns the maximum HTTP response body size accepted by the transport. */
    public function httpResponseBodyLimit(): HttpResponseBodyLimit;

    /** Returns the maximum encoded HTTP request body size accepted by configured operations. */
    public function httpRequestBodyLimit(): HttpRequestBodyLimit;

    /**
     * Returns the retry attempt and delay policy.
     *
     * The policy supplies retry limits and delays; it does not make an unsafe request retryable.
     */
    public function retryPolicy(): RetryPolicy;

    /** Whether retries are enabled for requests that explicitly declare retry safety. */
    public function retryEnabled(): bool;

    /** Returns the failure thresholds and timings used by the provider circuit breaker. */
    public function circuitBreakerPolicy(): CircuitBreakerPolicy;

    /** Whether the circuit-breaker layer is enabled. */
    public function circuitBreakerEnabled(): bool;

    /** Whether the shared 429 cooldown (backpressure) layer is enabled. */
    public function backpressureEnabled(): bool;

    /** Whether requests are serialized per rate-limit scope; false whenever backpressure is disabled. */
    public function executionLockEnabled(): bool;

    /** Fallback cooldown after a 429 response without a usable Retry-After value, in seconds. */
    public function rateLimitFallbackCooldownSeconds(): int;
}
