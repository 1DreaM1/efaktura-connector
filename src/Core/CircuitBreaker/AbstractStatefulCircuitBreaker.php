<?php

namespace DreaM1\EfakturaConnector\Core\CircuitBreaker;

/** Implements circuit-breaker operations over a state supplied by a concrete backend. */
abstract class AbstractStatefulCircuitBreaker implements CircuitBreakerInterface
{
    private ?CircuitBreakerPolicy $policy = null;

    /**
     * Uses the supplied thresholds, or the package defaults when omitted.
     *
     * @param CircuitBreakerPolicy|null $policy Failure thresholds and timing for state transitions.
     */
    public function __construct(?CircuitBreakerPolicy $policy = null)
    {
        $this->policy = $policy ?? new CircuitBreakerPolicy();
    }

    final public function isOpen(): bool
    {
        $policy = $this->circuitBreakerPolicy();

        return $this->updateState(static function (CircuitBreakerState $state, float $now) use ($policy): bool {
            return CircuitBreakerStateTransitions::isOpen($state, $now, $policy);
        });
    }

    final public function acquireExecutionPermit(): ?CircuitBreakerExecutionPermit
    {
        $policy = $this->circuitBreakerPolicy();

        return $this->updateState(static function (CircuitBreakerState $state, float $now) use ($policy): ?CircuitBreakerExecutionPermit {
            return CircuitBreakerStateTransitions::acquireExecutionPermit($state, $now, $policy);
        });
    }

    final public function recordSuccessForPermit(CircuitBreakerExecutionPermit $permit): void
    {
        $policy = $this->circuitBreakerPolicy();

        $this->updateState(static function (CircuitBreakerState $state, float $now) use ($policy, $permit): void {
            CircuitBreakerStateTransitions::recordSuccessForPermit($state, $now, $permit, $policy);
        });
    }

    final public function recordNeutralForPermit(CircuitBreakerExecutionPermit $permit): void
    {
        $policy = $this->circuitBreakerPolicy();

        $this->updateState(static function (CircuitBreakerState $state, float $now) use ($policy, $permit): void {
            CircuitBreakerStateTransitions::recordNeutralForPermit($state, $now, $permit, $policy);
        });
    }

    final public function recordFailureForPermit(CircuitBreakerExecutionPermit $permit): void
    {
        $policy = $this->circuitBreakerPolicy();

        $this->updateState(static function (CircuitBreakerState $state, float $now) use ($policy, $permit): void {
            CircuitBreakerStateTransitions::recordFailureForPermit($state, $now, $permit, $policy);
        });
    }

    /** Returns the policy used by every state transition. */
    protected function circuitBreakerPolicy(): CircuitBreakerPolicy
    {
        if ($this->policy === null) {
            $this->policy = new CircuitBreakerPolicy();
        }

        return $this->policy;
    }

    /**
     * Applies a state transition using the backend's storage and current time source.
     *
     * @param  callable(CircuitBreakerState, float): mixed $operation Callback that applies the operation to the current state.
     * @return mixed
     */
    abstract protected function updateState(callable $operation);
}
