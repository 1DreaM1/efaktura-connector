<?php

namespace DreaM1\EfakturaConnector\Core\CircuitBreaker;

/**
 * Process-local circuit breaker backed by an in-memory failure counter and timeout.
 *
 * By default, five connection or server failures within 60 seconds open the circuit for 30 seconds.
 * After that it becomes half-open and reserves one probe request; a successful probe resets the
 * history, while a failed probe opens the circuit again. State is process-local and not persisted.
 */
final class InMemoryCircuitBreaker extends AbstractStatefulCircuitBreaker
{
    private CircuitBreakerState $state;

    /** @var callable(): float */
    private $clock;

    /**
     * Creates an in-memory circuit breaker with an optional clock callback.
     * @param callable|null             $clock  Zero-argument callback returning the current Unix timestamp as a float;
     *                                          defaults to microtime(true).
     * @param CircuitBreakerPolicy|null $policy Failure thresholds and timing; defaults to the package policy.
     */
    public function __construct(?callable $clock = null, ?CircuitBreakerPolicy $policy = null)
    {
        parent::__construct($policy);
        $this->state = CircuitBreakerState::closed();
        $this->clock = $clock ?? static fn (): float => microtime(true);
    }

    /** @param callable(CircuitBreakerState, float): mixed $operation Callback that applies the operation to the current state. */
    protected function updateState(callable $operation)
    {
        return $operation($this->state, ($this->clock)());
    }
}
