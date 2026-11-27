<?php

namespace DreaM1\EfakturaConnector\Core\CircuitBreaker;

/**
 * Admits requests to a provider and records their outcomes.
 *
 * Every admitted request carries a permit, so a late outcome cannot change the state of a newer
 * breaker generation and a half-open probe is reserved atomically.
 */
interface CircuitBreakerInterface
{
    /**
     * Whether the breaker currently prevents an HTTP attempt.
     *
     * @return bool
     */
    public function isOpen(): bool;

    /** Atomically admits a request and returns the permit that must report its eventual outcome. */
    public function acquireExecutionPermit(): ?CircuitBreakerExecutionPermit;

    /**
     * Reports a successful request admitted by the supplied permit.
     *
     * @param CircuitBreakerExecutionPermit $permit Permit returned when the request was admitted.
     */
    public function recordSuccessForPermit(CircuitBreakerExecutionPermit $permit): void;

    /**
     * Reports a failed request admitted by the supplied permit.
     *
     * @param CircuitBreakerExecutionPermit $permit Permit returned when the request was admitted.
     */
    public function recordFailureForPermit(CircuitBreakerExecutionPermit $permit): void;

    /**
     * Releases an admitted request without changing provider failure history.
     *
     * @param CircuitBreakerExecutionPermit $permit Permit returned when the request was admitted.
     */
    public function recordNeutralForPermit(CircuitBreakerExecutionPermit $permit): void;
}
