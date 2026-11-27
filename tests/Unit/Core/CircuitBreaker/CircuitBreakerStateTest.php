<?php

namespace DreaM1\EfakturaConnector\Tests\Unit\Core\CircuitBreaker;

use DreaM1\EfakturaConnector\Core\CircuitBreaker\CircuitBreakerState;
use PHPUnit\Framework\TestCase;

/** Covers creation, validation of stored state, transitions and generation handling. */
class CircuitBreakerStateTest extends TestCase
{
    public function testClosedStateIsEmptyAndHasAGeneration(): void
    {
        $state = CircuitBreakerState::closed();

        $this->assertSame(CircuitBreakerState::STATE_CLOSED, $state->state());
        $this->assertSame([], $state->failureTimes());
        $this->assertNull($state->openedAt());
        $this->assertFalse($state->probeInFlight());
        $this->assertSame(32, strlen($state->generation()));
        $this->assertNotSame($state->generation(), CircuitBreakerState::closed()->generation());
    }

    public function testRoundTripsEveryStateThroughItsArrayForm(): void
    {
        $open = CircuitBreakerState::closed();
        $open->addFailureTime(1.5);
        $open->open(10.25, 'generation-a');

        $halfOpen = CircuitBreakerState::fromArray($open->toArray());
        $halfOpen->moveToHalfOpen();
        $halfOpen->setProbeInFlight(true);

        foreach ([CircuitBreakerState::closed(), $open, $halfOpen] as $state) {
            $restored = CircuitBreakerState::fromArray($state->toArray());

            $this->assertSame($state->toArray(), $restored->toArray());
        }

        $this->assertSame(CircuitBreakerState::STATE_OPEN, $open->state());
        $this->assertSame(10.25, $open->openedAt());
        $this->assertSame('generation-a', $open->generation());
        $this->assertSame([1.5], $open->failureTimes());
    }

    public function testNonArrayStorageValueBecomesAFreshClosedState(): void
    {
        foreach ([null, 'OPEN', 1, true] as $value) {
            $state = CircuitBreakerState::fromArray($value);

            $this->assertSame(CircuitBreakerState::STATE_CLOSED, $state->state());
            $this->assertSame([], $state->failureTimes());
        }
    }

    /**
     * @dataProvider invalidStoredStates
     * @param array<string, mixed> $stored
     */
    public function testInvalidStoredStateIsDiscardedAndClosedAgain(array $stored): void
    {
        $state = CircuitBreakerState::fromArray($stored);

        $this->assertSame(CircuitBreakerState::STATE_CLOSED, $state->state());
        $this->assertSame([], $state->failureTimes());
        $this->assertNull($state->openedAt());
        $this->assertFalse($state->probeInFlight());
    }

    /** @return array<string, array{array<string, mixed>}> */
    public function invalidStoredStates(): array
    {
        $valid = [
            'state'           => CircuitBreakerState::STATE_OPEN,
            'failure_times'   => [1.0],
            'opened_at'       => 5.0,
            'probe_in_flight' => false,
            'generation'      => 'generation-a',
        ];

        return [
            'missing state'               => [array_diff_key($valid, ['state' => true])],
            'unknown state'               => [array_merge($valid, ['state' => 'BROKEN'])],
            'lowercase state'             => [array_merge($valid, ['state' => 'open'])],
            'failure times not an array'  => [array_merge($valid, ['failure_times' => '1.0'])],
            'missing failure times'       => [array_diff_key($valid, ['failure_times' => true])],
            'non numeric opened at'       => [array_merge($valid, ['opened_at' => 'yesterday'])],
            'open without opened at'      => [array_merge($valid, ['opened_at' => null])],
            'probe flag not boolean'      => [array_merge($valid, ['probe_in_flight' => 1])],
            'missing probe flag'          => [array_diff_key($valid, ['probe_in_flight' => true])],
            'probe reserved while open'   => [array_merge($valid, ['probe_in_flight' => true])],
            'probe reserved while closed' => [array_merge($valid, [
                'state'           => CircuitBreakerState::STATE_CLOSED,
                'opened_at'       => null,
                'probe_in_flight' => true,
            ])],
            'generation not a string' => [array_merge($valid, ['generation' => 123])],
            'blank generation'        => [array_merge($valid, ['generation' => '   '])],
        ];
    }

    public function testNormalizesFailureTimesAndOpenedAtFromStorage(): void
    {
        $state = CircuitBreakerState::fromArray([
            'state'           => CircuitBreakerState::STATE_CLOSED,
            'failure_times'   => ['1.5', 2, 'bad', null, 3.25, [4]],
            'opened_at'       => '7.5',
            'probe_in_flight' => false,
            'generation'      => 'generation-a',
        ]);

        $this->assertSame([1.5, 2.0, 3.25], $state->failureTimes());
        $this->assertSame(7.5, $state->openedAt());
    }

    public function testMissingGenerationIsReplacedWithANewOne(): void
    {
        $state = CircuitBreakerState::fromArray([
            'state'           => CircuitBreakerState::STATE_CLOSED,
            'failure_times'   => [],
            'opened_at'       => null,
            'probe_in_flight' => false,
        ]);

        $this->assertSame(CircuitBreakerState::STATE_CLOSED, $state->state());
        $this->assertSame(32, strlen($state->generation()));
    }

    public function testHalfOpenStateKeepsAProbeReservationFromStorage(): void
    {
        $state = CircuitBreakerState::fromArray([
            'state'           => CircuitBreakerState::STATE_HALF_OPEN,
            'failure_times'   => [],
            'opened_at'       => 5.0,
            'probe_in_flight' => true,
            'generation'      => 'generation-a',
        ]);

        $this->assertSame(CircuitBreakerState::STATE_HALF_OPEN, $state->state());
        $this->assertTrue($state->probeInFlight());
    }

    public function testMovingToHalfOpenClearsTheProbeReservation(): void
    {
        $state = CircuitBreakerState::closed();
        $state->open(5.0, 'generation-a');
        $state->setProbeInFlight(true);

        $state->moveToHalfOpen();

        $this->assertSame(CircuitBreakerState::STATE_HALF_OPEN, $state->state());
        $this->assertFalse($state->probeInFlight());
        $this->assertSame(5.0, $state->openedAt());
        $this->assertSame('generation-a', $state->generation());
    }

    public function testOpeningRecordsTimeGenerationAndReleasesTheProbe(): void
    {
        $state = CircuitBreakerState::closed();
        $state->setProbeInFlight(true);

        $state->open(12.5, 'generation-b');

        $this->assertSame(CircuitBreakerState::STATE_OPEN, $state->state());
        $this->assertSame(12.5, $state->openedAt());
        $this->assertSame('generation-b', $state->generation());
        $this->assertFalse($state->probeInFlight());
    }

    public function testFailureTimesCanBeAppendedAndReplaced(): void
    {
        $state = CircuitBreakerState::closed();

        $state->addFailureTime(1.0);
        $state->addFailureTime(2.0);
        $this->assertSame([1.0, 2.0], $state->failureTimes());

        $state->setFailureTimes([2.0]);
        $this->assertSame([2.0], $state->failureTimes());
    }

    public function testResetClosedClearsEverythingAndInvalidatesTheGeneration(): void
    {
        $state = CircuitBreakerState::closed();
        $state->addFailureTime(1.0);
        $state->open(5.0, 'generation-a');
        $state->setProbeInFlight(true);

        $state->resetClosed();

        $this->assertSame(CircuitBreakerState::STATE_CLOSED, $state->state());
        $this->assertSame([], $state->failureTimes());
        $this->assertNull($state->openedAt());
        $this->assertFalse($state->probeInFlight());
        $this->assertNotSame('generation-a', $state->generation());
        $this->assertSame(32, strlen($state->generation()));
    }

    public function testProbeStartTimeIsStoredWithTheReservationAndClearedOnRelease(): void
    {
        $state = CircuitBreakerState::closed();
        $state->open(5.0, 'generation-a');
        $state->moveToHalfOpen();
        $this->assertNull($state->probeStartedAt());

        $state->setProbeInFlight(true, 40.5);
        $this->assertSame(40.5, $state->probeStartedAt());
        $this->assertSame(40.5, CircuitBreakerState::fromArray($state->toArray())->probeStartedAt());

        $state->setProbeInFlight(false);
        $this->assertNull($state->probeStartedAt());
        $this->assertNull($state->toArray()['probe_started_at']);
    }

    public function testStoredStateWithoutAProbeStartTimeStillLoads(): void
    {
        $state = CircuitBreakerState::fromArray([
            'state'           => CircuitBreakerState::STATE_HALF_OPEN,
            'failure_times'   => [],
            'opened_at'       => 5.0,
            'probe_in_flight' => true,
            'generation'      => 'generation-a',
        ]);

        $this->assertTrue($state->probeInFlight());
        $this->assertNull($state->probeStartedAt());
    }

    public function testNonNumericProbeStartTimeDiscardsTheStoredState(): void
    {
        $state = CircuitBreakerState::fromArray([
            'state'            => CircuitBreakerState::STATE_HALF_OPEN,
            'failure_times'    => [],
            'opened_at'        => 5.0,
            'probe_in_flight'  => true,
            'probe_started_at' => 'yesterday',
            'generation'       => 'generation-a',
        ]);

        $this->assertSame(CircuitBreakerState::STATE_CLOSED, $state->state());
        $this->assertNull($state->probeStartedAt());
    }

    public function testProbeStartTimeWithoutAReservationIsDropped(): void
    {
        $state = CircuitBreakerState::fromArray([
            'state'            => CircuitBreakerState::STATE_HALF_OPEN,
            'failure_times'    => [],
            'opened_at'        => 5.0,
            'probe_in_flight'  => false,
            'probe_started_at' => 40.0,
            'generation'       => 'generation-a',
        ]);

        $this->assertFalse($state->probeInFlight());
        $this->assertNull($state->probeStartedAt());
    }

    public function testDiscardingALostProbeReleasesItAndStartsANewGeneration(): void
    {
        $state = CircuitBreakerState::closed();
        $state->open(5.0, 'generation-a');
        $state->moveToHalfOpen();
        $state->setProbeInFlight(true, 40.0);

        $state->discardLostProbe();

        $this->assertSame(CircuitBreakerState::STATE_HALF_OPEN, $state->state());
        $this->assertFalse($state->probeInFlight());
        $this->assertNull($state->probeStartedAt());
        $this->assertNotSame('generation-a', $state->generation());
    }
    public function testGenerationIdentifiersAreUniqueHex(): void
    {
        $first  = CircuitBreakerState::newGenerationIdentifier();
        $second = CircuitBreakerState::newGenerationIdentifier();

        $this->assertMatchesRegularExpression('/^[0-9a-f]{32}$/', $first);
        $this->assertNotSame($first, $second);
    }
}
