<?php

namespace DreaM1\EfakturaConnector\Core\CircuitBreaker;

/** Default failure thresholds shared by circuit breaker implementations. */
final class CircuitBreakerDefaults
{
    /** Number of failures inside the configured window that opens the circuit. */
    public const FAILURE_THRESHOLD = 5;
    /** Rolling period, in seconds, used to count circuit-opening failures. */
    public const FAILURE_WINDOW_SECONDS = 60;
    /** Time, in seconds, that the circuit remains open before a probe is allowed. */
    public const OPEN_DURATION_SECONDS = 30;
}
