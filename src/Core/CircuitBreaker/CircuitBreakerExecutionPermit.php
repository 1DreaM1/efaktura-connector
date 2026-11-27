<?php

namespace DreaM1\EfakturaConnector\Core\CircuitBreaker;

use DreaM1\EfakturaConnector\Support\Exceptions\InvalidInputException;

/** Identifies the breaker generation and probe state for one admitted request. */
final class CircuitBreakerExecutionPermit
{
    private string $generation;
    private bool $halfOpenProbe;

    /**
     * Creates a permit for a request admitted in a specific breaker generation.
     *
     * @param  string                $generation    Opaque breaker generation that admitted the request.
     * @param  bool                  $halfOpenProbe Whether this request is the single probe in the half-open state.
     * @throws InvalidInputException when the generation identifier is empty
     */
    public function __construct(string $generation, bool $halfOpenProbe)
    {
        if (trim($generation) === '') {
            throw new InvalidInputException('Circuit-breaker permit generation must not be empty.');
        }

        $this->generation    = $generation;
        $this->halfOpenProbe = $halfOpenProbe;
    }

    /** Returns the breaker generation that admitted this request. */
    public function generation(): string
    {
        return $this->generation;
    }

    /** Whether this permit represents the half-open probe. */
    public function isHalfOpenProbe(): bool
    {
        return $this->halfOpenProbe;
    }
}
