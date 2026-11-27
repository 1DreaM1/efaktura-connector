<?php

namespace DreaM1\EfakturaConnector\Core\CircuitBreaker;

/** @internal Validated circuit-breaker state used by the transition logic. */
final class CircuitBreakerState
{
    /** Serialized label for a circuit that admits requests and records failures. */
    public const STATE_CLOSED = 'CLOSED';
    /** Serialized label for a circuit that rejects requests until its open period expires. */
    public const STATE_OPEN = 'OPEN';
    /** Serialized label for a circuit that admits one probe request. */
    public const STATE_HALF_OPEN = 'HALF_OPEN';

    /** State-array key containing the circuit state label. */
    public const FIELD_STATE = 'state';
    /** State-array key containing failure timestamps in the active window. */
    public const FIELD_FAILURE_TIMES = 'failure_times';
    /** State-array key containing the time the circuit opened, or null. */
    public const FIELD_OPENED_AT = 'opened_at';
    /** State-array key indicating whether the half-open probe is reserved. */
    public const FIELD_PROBE_IN_FLIGHT = 'probe_in_flight';
    /** State-array key identifying the generation that admitted request permits. */
    public const FIELD_GENERATION = 'generation';
    /** State-array key containing the time the half-open probe was admitted, or null. */
    public const FIELD_PROBE_STARTED_AT = 'probe_started_at';

    private string $state;
    /** @var float[] */
    private array $failureTimes;
    private ?float $openedAt;
    private bool $probeInFlight;
    private ?float $probeStartedAt;
    private string $generation;

    /** @param float[] $failureTimes */
    private function __construct(
        string $state,
        array $failureTimes,
        ?float $openedAt,
        bool $probeInFlight,
        ?float $probeStartedAt,
        string $generation
    ) {
        $this->state          = $state;
        $this->failureTimes   = $failureTimes;
        $this->openedAt       = $openedAt;
        $this->probeInFlight  = $probeInFlight;
        $this->probeStartedAt = $probeInFlight ? $probeStartedAt : null;
        $this->generation     = $generation;
    }

    /** Creates a fresh closed state. */
    public static function closed(): self
    {
        return new self(self::STATE_CLOSED, [], null, false, null, self::newGenerationIdentifier());
    }

    /**
     * Rebuilds a validated state from the array representation used by storage backends.
     *
     * @param mixed $value State loaded from storage.
     */
    public static function fromArray($value): self
    {
        if (! is_array($value)) {
            return self::closed();
        }

        $state         = $value[self::FIELD_STATE] ?? null;
        $failureTimes  = $value[self::FIELD_FAILURE_TIMES] ?? null;
        $openedAt      = $value[self::FIELD_OPENED_AT] ?? null;
        $probeInFlight = $value[self::FIELD_PROBE_IN_FLIGHT] ?? null;
        $generation    = $value[self::FIELD_GENERATION] ?? self::newGenerationIdentifier();
        // State stored before probe leases existed has no start time; such a probe counts as already expired.
        $probeStartedAt = $value[self::FIELD_PROBE_STARTED_AT] ?? null;

        if (
            ! in_array($state, [self::STATE_CLOSED, self::STATE_OPEN, self::STATE_HALF_OPEN], true)
            || ! is_array($failureTimes)
            || ($probeStartedAt !== null && ! is_numeric($probeStartedAt))
            || ($openedAt !== null && ! is_numeric($openedAt))
            || ($state === self::STATE_OPEN && $openedAt === null)
            || ! is_bool($probeInFlight)
            || ($state !== self::STATE_HALF_OPEN && $probeInFlight)
            || ! is_string($generation)
            || trim($generation) === ''
        ) {
            return self::closed();
        }

        $failureTimes = array_values(array_filter($failureTimes, static function ($time): bool {
            return is_numeric($time);
        }));

        return new self(
            $state,
            array_map('floatval', $failureTimes),
            $openedAt === null ? null : (float) $openedAt,
            $probeInFlight,
            $probeStartedAt === null ? null : (float) $probeStartedAt,
            $generation
        );
    }

    /** @return array{state: string, failure_times: array<int, float>, opened_at: float|null, probe_in_flight: bool, probe_started_at: float|null, generation: string} */
    public function toArray(): array
    {
        return [
            self::FIELD_STATE            => $this->state,
            self::FIELD_FAILURE_TIMES    => $this->failureTimes,
            self::FIELD_OPENED_AT        => $this->openedAt,
            self::FIELD_PROBE_IN_FLIGHT  => $this->probeInFlight,
            self::FIELD_PROBE_STARTED_AT => $this->probeStartedAt,
            self::FIELD_GENERATION       => $this->generation,
        ];
    }

    /** Creates an opaque generation ID used to reject outcomes from stale requests. */
    public static function newGenerationIdentifier(): string
    {
        return bin2hex(random_bytes(16));
    }

    /** Returns the current closed, open, or half-open state label. */
    public function state(): string
    {
        return $this->state;
    }

    /** Moves an expired open circuit to half-open and clears its probe reservation. */
    public function moveToHalfOpen(): void
    {
        $this->state          = self::STATE_HALF_OPEN;
        $this->probeInFlight  = false;
        $this->probeStartedAt = null;
    }

    /** Marks the circuit open at the supplied time and stores its generation ID. */
    public function open(float $openedAt, string $generation): void
    {
        $this->state          = self::STATE_OPEN;
        $this->openedAt       = $openedAt;
        $this->probeInFlight  = false;
        $this->probeStartedAt = null;
        $this->generation     = $generation;
    }

    /** @return float[] */
    public function failureTimes(): array
    {
        return $this->failureTimes;
    }

    /** @param float[] $failureTimes */
    public function setFailureTimes(array $failureTimes): void
    {
        $this->failureTimes = $failureTimes;
    }

    /** Appends one provider failure timestamp before the policy prunes its history. */
    public function addFailureTime(float $failureTime): void
    {
        $this->failureTimes[] = $failureTime;
    }

    /** Returns when this generation opened, or null when it has not opened. */
    public function openedAt(): ?float
    {
        return $this->openedAt;
    }

    /** Whether a half-open probe currently holds the single execution reservation. */
    public function probeInFlight(): bool
    {
        return $this->probeInFlight;
    }

    /** Returns when the current half-open probe was admitted, or null when none is reserved or its time is unknown. */
    public function probeStartedAt(): ?float
    {
        return $this->probeStartedAt;
    }

    /**
     * Updates the half-open probe reservation after admission or completion.
     *
     * @param bool       $probeInFlight Whether a probe now holds the reservation.
     * @param float|null $startedAt     Admission time recorded with a new reservation; cleared on release.
     */
    public function setProbeInFlight(bool $probeInFlight, ?float $startedAt = null): void
    {
        $this->probeInFlight  = $probeInFlight;
        $this->probeStartedAt = $probeInFlight ? $startedAt : null;
    }

    /** Releases a probe whose outcome never arrived and invalidates its permit by starting a new generation. */
    public function discardLostProbe(): void
    {
        $this->probeInFlight  = false;
        $this->probeStartedAt = null;
        $this->generation     = self::newGenerationIdentifier();
    }

    /** Returns the opaque ID used to match request outcomes to this state generation. */
    public function generation(): string
    {
        return $this->generation;
    }

    /** Resets the state and invalidates permits from the previous generation. */
    public function resetClosed(): void
    {
        $closed               = self::closed();
        $this->state          = $closed->state;
        $this->failureTimes   = $closed->failureTimes;
        $this->openedAt       = $closed->openedAt;
        $this->probeInFlight  = $closed->probeInFlight;
        $this->probeStartedAt = $closed->probeStartedAt;
        $this->generation     = $closed->generation;
    }
}
