<?php

namespace DreaM1\EfakturaConnector\Core\CircuitBreaker;

use DreaM1\EfakturaConnector\Support\Exceptions\InvalidInputException;

/** Immutable circuit-breaker thresholds and timing windows. */
final class CircuitBreakerPolicy
{
    private int $failureThreshold;
    private int $failureWindowSeconds;
    private int $openDurationSeconds;

    /**
     * Sets the failure threshold and circuit timing windows.
     *
     * @param  int                   $failureThreshold     Failures required to open the circuit within the rolling window.
     * @param  int                   $failureWindowSeconds Time window in which failures are counted, in seconds.
     * @param  int                   $openDurationSeconds  Time the circuit remains open before one probe is allowed, in seconds.
     * @throws InvalidInputException when any threshold or duration is less than one
     */
    public function __construct(
        int $failureThreshold = CircuitBreakerDefaults::FAILURE_THRESHOLD,
        int $failureWindowSeconds = CircuitBreakerDefaults::FAILURE_WINDOW_SECONDS,
        int $openDurationSeconds = CircuitBreakerDefaults::OPEN_DURATION_SECONDS
    ) {
        if ($failureThreshold < 1 || $failureWindowSeconds < 1 || $openDurationSeconds < 1) {
            throw new InvalidInputException('Circuit-breaker thresholds and durations must be positive.');
        }

        $this->failureThreshold     = $failureThreshold;
        $this->failureWindowSeconds = $failureWindowSeconds;
        $this->openDurationSeconds  = $openDurationSeconds;
    }

    /** Returns the number of failures required to open the circuit. */
    public function failureThreshold(): int
    {
        return $this->failureThreshold;
    }

    /** Returns the rolling failure-counting window in seconds. */
    public function failureWindowSeconds(): int
    {
        return $this->failureWindowSeconds;
    }

    /** Returns how long the circuit stays open before a probe may run, in seconds. */
    public function openDurationSeconds(): int
    {
        return $this->openDurationSeconds;
    }
}
