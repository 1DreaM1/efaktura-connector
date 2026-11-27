<?php

namespace DreaM1\EfakturaConnector\Core\CircuitBreaker;

/** @internal Applies typed, storage-independent circuit-breaker transitions. */
final class CircuitBreakerStateTransitions
{
    private function __construct()
    {
    }

    public static function isOpen(CircuitBreakerState $state, float $now, CircuitBreakerPolicy $policy): bool
    {
        self::transitionIfNeeded($state, $now, $policy);

        return $state->state() === CircuitBreakerState::STATE_OPEN;
    }

    public static function acquireExecutionPermit(
        CircuitBreakerState $state,
        float $now,
        CircuitBreakerPolicy $policy
    ): ?CircuitBreakerExecutionPermit {
        self::transitionIfNeeded($state, $now, $policy);

        if ($state->state() === CircuitBreakerState::STATE_OPEN) {
            return null;
        }

        $halfOpenProbe = $state->state() === CircuitBreakerState::STATE_HALF_OPEN;

        if ($halfOpenProbe) {
            if ($state->probeInFlight()) {
                if (! self::probeLeaseExpired($state, $now, $policy)) {
                    return null;
                }

                // The probe's worker died or hung without reporting an outcome; without this the circuit would
                // reject every request forever. A late outcome from the old probe is ignored by its generation.
                $state->discardLostProbe();
            }

            $state->setProbeInFlight(true, $now);
        }

        return new CircuitBreakerExecutionPermit($state->generation(), $halfOpenProbe);
    }

    public static function recordSuccessForPermit(
        CircuitBreakerState $state,
        float $now,
        CircuitBreakerExecutionPermit $permit,
        CircuitBreakerPolicy $policy
    ): void {
        self::transitionIfNeeded($state, $now, $policy);

        if (
            self::permitMatchesGeneration($state, $permit)
            && $permit->isHalfOpenProbe()
            && $state->state() === CircuitBreakerState::STATE_HALF_OPEN
            && $state->probeInFlight()
        ) {
            $state->resetClosed();
        }
    }

    public static function recordNeutralForPermit(
        CircuitBreakerState $state,
        float $now,
        CircuitBreakerExecutionPermit $permit,
        CircuitBreakerPolicy $policy
    ): void {
        self::transitionIfNeeded($state, $now, $policy);

        if (
            self::permitMatchesGeneration($state, $permit)
            && $permit->isHalfOpenProbe()
            && $state->state() === CircuitBreakerState::STATE_HALF_OPEN
            && $state->probeInFlight()
        ) {
            $state->setProbeInFlight(false);
        }
    }

    public static function recordFailureForPermit(
        CircuitBreakerState $state,
        float $now,
        CircuitBreakerExecutionPermit $permit,
        CircuitBreakerPolicy $policy
    ): void {
        self::transitionIfNeeded($state, $now, $policy);

        if (! self::permitMatchesGeneration($state, $permit)) {
            return;
        }

        if (
            $permit->isHalfOpenProbe()
            && (
                $state->state() !== CircuitBreakerState::STATE_HALF_OPEN
                || ! $state->probeInFlight()
            )
        ) {
            return;
        }

        if (! $permit->isHalfOpenProbe() && $state->state() !== CircuitBreakerState::STATE_CLOSED) {
            return;
        }

        self::recordFailureInState($state, $now, $policy);
    }

    private static function recordFailureInState(
        CircuitBreakerState $state,
        float $now,
        CircuitBreakerPolicy $policy
    ): void {
        self::transitionIfNeeded($state, $now, $policy);
        $state->addFailureTime($now);
        $state->setFailureTimes(self::pruneOldFailures($state->failureTimes(), $now, $policy));

        if (
            $state->state() === CircuitBreakerState::STATE_HALF_OPEN
            || count($state->failureTimes()) >= $policy->failureThreshold()
        ) {
            $state->open($now, CircuitBreakerState::newGenerationIdentifier());
        }

        $state->setProbeInFlight(false);
    }

    private static function transitionIfNeeded(
        CircuitBreakerState $state,
        float $now,
        CircuitBreakerPolicy $policy
    ): void {
        if ($state->state() !== CircuitBreakerState::STATE_OPEN) {
            return;
        }

        $openedAt = $state->openedAt();

        if ($openedAt !== null && $now - $openedAt >= $policy->openDurationSeconds()) {
            $state->moveToHalfOpen();
        }
    }

    /**
     * Whether the reserved half-open probe has had one open period to report and is treated as lost.
     * A reservation without a recorded start time (state stored by an earlier version) counts as expired.
     */
    private static function probeLeaseExpired(
        CircuitBreakerState $state,
        float $now,
        CircuitBreakerPolicy $policy
    ): bool {
        $startedAt = $state->probeStartedAt();

        return $startedAt === null || $now - $startedAt >= $policy->openDurationSeconds();
    }

    /**
     * @param  float[] $failureTimes
     * @return float[]
     */
    private static function pruneOldFailures(array $failureTimes, float $now, CircuitBreakerPolicy $policy): array
    {
        return array_values(array_filter(
            $failureTimes,
            static function ($time) use ($now, $policy): bool {
                return is_numeric($time) && ($now - (float) $time) < $policy->failureWindowSeconds();
            }
        ));
    }

    private static function permitMatchesGeneration(
        CircuitBreakerState $state,
        CircuitBreakerExecutionPermit $permit
    ): bool {
        return $state->generation() === $permit->generation();
    }
}
