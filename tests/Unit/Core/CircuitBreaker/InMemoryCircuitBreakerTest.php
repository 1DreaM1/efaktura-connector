<?php

namespace DreaM1\EfakturaConnector\Tests\Unit\Core\CircuitBreaker;

use DreaM1\EfakturaConnector\Core\CircuitBreaker\CircuitBreakerPolicy;
use DreaM1\EfakturaConnector\Core\CircuitBreaker\InMemoryCircuitBreaker;
use PHPUnit\Framework\TestCase;

/** Covers failure windows, open and half-open transitions, probe permits and stale outcomes. */
class InMemoryCircuitBreakerTest extends TestCase
{
    public function testNewCircuitBreakerIsClosed(): void
    {
        $breaker = new InMemoryCircuitBreaker();
        $this->assertFalse($breaker->isOpen());
    }

    public function testCircuitOpensAfterThresholdFailures(): void
    {
        $time    = 0.0;
        $breaker = $this->makeBreaker($time);
        for ($i = 0; $i < 5; $i++) {
            $this->failOnce($breaker);
        }

        $this->assertTrue($breaker->isOpen());
    }

    public function testCircuitRemainsClosedBelowThreshold(): void
    {
        $time    = 0.0;
        $breaker = $this->makeBreaker($time);
        for ($i = 0; $i < 4; $i++) {
            $this->failOnce($breaker);
        }

        $this->assertFalse($breaker->isOpen());
    }

    public function testConfiguredPolicyControlsFailureThresholdWindowAndOpenDuration(): void
    {
        $time    = 0.0;
        $breaker = new InMemoryCircuitBreaker(
            static function () use (&$time): float {
                return $time;
            },
            new CircuitBreakerPolicy(2, 10, 5)
        );

        $this->failOnce($breaker);
        $this->failOnce($breaker);
        $this->assertTrue($breaker->isOpen());

        $time = 5.0;
        $this->assertFalse($breaker->isOpen());
        $this->assertNotNull($breaker->acquireExecutionPermit());
    }

    public function testCircuitTransitionsToHalfOpenAfterOpenDuration(): void
    {
        $time    = 0.0;
        $breaker = $this->makeBreaker($time);

        for ($i = 0; $i < 5; $i++) {
            $this->failOnce($breaker);
        }

        $this->assertTrue($breaker->isOpen());

        $time = 31.0; // past the 30-second open window
        $this->assertFalse($breaker->isOpen()); // now HALF_OPEN → not open
    }

    public function testHalfOpenCircuitAllowsOnlyOneProbeAtATime(): void
    {
        $time    = 0.0;
        $breaker = $this->makeBreaker($time);
        for ($i = 0; $i < 5; $i++) {
            $this->failOnce($breaker);
        }

        $time  = 31.0;
        $probe = $breaker->acquireExecutionPermit();
        $this->assertNotNull($probe);
        $this->assertNull($breaker->acquireExecutionPermit());
        $breaker->recordNeutralForPermit($probe);
        $this->assertNotNull($breaker->acquireExecutionPermit());
    }

    public function testSuccessInHalfOpenClosesCircuit(): void
    {
        $time    = 0.0;
        $breaker = $this->makeBreaker($time);

        for ($i = 0; $i < 5; $i++) {
            $this->failOnce($breaker);
        }

        $time  = 31.0;
        $probe = $breaker->acquireExecutionPermit();
        $this->assertNotNull($probe);
        $breaker->recordSuccessForPermit($probe); // triggers HALF_OPEN → CLOSED
        $this->assertFalse($breaker->isOpen());
    }

    public function testStaleSuccessCannotCloseAnOpenCircuit(): void
    {
        $time    = 0.0;
        $breaker = $this->makeBreaker($time, new CircuitBreakerPolicy(1, 60, 30));
        $permit  = $breaker->acquireExecutionPermit();
        $this->assertNotNull($permit);

        $this->failOnce($breaker);
        $this->assertTrue($breaker->isOpen());

        $breaker->recordSuccessForPermit($permit);

        $this->assertTrue($breaker->isOpen());
    }

    public function testStaleOutcomesCannotCompleteAProbeOrChangeARecoveredCircuit(): void
    {
        $time        = 0.0;
        $breaker     = $this->makeBreaker($time, new CircuitBreakerPolicy(1, 60, 30));
        $stalePermit = $breaker->acquireExecutionPermit();
        $this->assertNotNull($stalePermit);
        $this->failOnce($breaker);

        $time        = 30.0;
        $probePermit = $breaker->acquireExecutionPermit();
        $this->assertNotNull($probePermit);
        $this->assertTrue($probePermit->isHalfOpenProbe());

        $breaker->recordSuccessForPermit($stalePermit);
        $breaker->recordNeutralForPermit($stalePermit);
        $this->assertNull($breaker->acquireExecutionPermit());

        $breaker->recordFailureForPermit($probePermit);
        $this->assertTrue($breaker->isOpen());

        $time           = 60.0;
        $recoveryPermit = $breaker->acquireExecutionPermit();
        $this->assertNotNull($recoveryPermit);
        $breaker->recordSuccessForPermit($recoveryPermit);
        $breaker->recordFailureForPermit($stalePermit);

        $this->assertFalse($breaker->isOpen());
    }

    public function testFailureInHalfOpenReopensCircuit(): void
    {
        $time    = 0.0;
        $breaker = $this->makeBreaker($time);

        for ($i = 0; $i < 5; $i++) {
            $this->failOnce($breaker);
        }

        $time  = 31.0; // → HALF_OPEN on next check
        $probe = $breaker->acquireExecutionPermit();
        $this->assertNotNull($probe);
        $breaker->recordFailureForPermit($probe); // in HALF_OPEN → OPEN again
        $this->assertTrue($breaker->isOpen());
    }

    public function testFailuresOutsideWindowDontCountTowardThreshold(): void
    {
        $time    = 0.0;
        $breaker = $this->makeBreaker($time);

        for ($i = 0; $i < 4; $i++) {
            $this->failOnce($breaker);
        }

        // Advance past the 60-second failure window so old failures expire
        $time = 61.0;
        $this->failOnce($breaker); // only 1 fresh failure — below threshold
        $this->assertFalse($breaker->isOpen());
    }

    public function testSuccessOnClosedCircuitDoesNothing(): void
    {
        $time    = 0.0;
        $breaker = $this->makeBreaker($time);
        $permit  = $breaker->acquireExecutionPermit();
        $this->assertNotNull($permit);
        $breaker->recordSuccessForPermit($permit);
        $this->assertFalse($breaker->isOpen());
    }

    public function testLostProbeIsReplacedOnceItsLeaseExpires(): void
    {
        $time    = 0.0;
        $breaker = $this->makeBreaker($time);
        for ($i = 0; $i < 5; $i++) {
            $this->failOnce($breaker);
        }

        $time = 31.0;
        $this->assertNotNull($breaker->acquireExecutionPermit()); // the worker dies without reporting an outcome

        $time = 60.0; // still inside the 30 second lease
        $this->assertNull($breaker->acquireExecutionPermit());

        $time        = 61.0; // lease expired
        $replacement = $breaker->acquireExecutionPermit();
        $this->assertNotNull($replacement);
        $this->assertTrue($replacement->isHalfOpenProbe());
        $this->assertNull($breaker->acquireExecutionPermit(), 'The replacement probe holds the single reservation.');

        $breaker->recordSuccessForPermit($replacement);
        $this->assertFalse($breaker->isOpen());
        $this->assertNotNull($breaker->acquireExecutionPermit());
    }

    public function testOutcomeOfAReplacedLostProbeIsIgnored(): void
    {
        $time    = 0.0;
        $breaker = $this->makeBreaker($time);
        for ($i = 0; $i < 5; $i++) {
            $this->failOnce($breaker);
        }

        $time  = 31.0;
        $lost  = $breaker->acquireExecutionPermit();
        $time  = 61.0;
        $fresh = $breaker->acquireExecutionPermit();
        $this->assertNotNull($lost);
        $this->assertNotNull($fresh);

        // The slow original probe finally answers; it must neither close the circuit nor free the new probe.
        $breaker->recordSuccessForPermit($lost);
        $breaker->recordFailureForPermit($lost);
        $breaker->recordNeutralForPermit($lost);

        $this->assertNull($breaker->acquireExecutionPermit(), 'The fresh probe is still in flight.');
        $breaker->recordSuccessForPermit($fresh);
        $this->assertNotNull($breaker->acquireExecutionPermit());
    }

    public function testReplacementProbeFailureReopensTheCircuit(): void
    {
        $time    = 0.0;
        $breaker = $this->makeBreaker($time);
        for ($i = 0; $i < 5; $i++) {
            $this->failOnce($breaker);
        }

        $time = 31.0;
        $breaker->acquireExecutionPermit();
        $time        = 61.0;
        $replacement = $breaker->acquireExecutionPermit();
        $this->assertNotNull($replacement);

        $breaker->recordFailureForPermit($replacement);

        $this->assertTrue($breaker->isOpen());
        $this->assertNull($breaker->acquireExecutionPermit());
    }

    public function testProbeLeaseFollowsTheConfiguredOpenDuration(): void
    {
        $time    = 0.0;
        $breaker = $this->makeBreaker($time, new CircuitBreakerPolicy(2, 10, 5));
        $this->failOnce($breaker);
        $this->failOnce($breaker);

        $time = 5.0;
        $this->assertNotNull($breaker->acquireExecutionPermit());

        $time = 9.0; // 4 seconds into a 5 second lease
        $this->assertNull($breaker->acquireExecutionPermit());

        $time = 10.0; // 5 seconds: lease expired
        $this->assertNotNull($breaker->acquireExecutionPermit());
    }
    /** Admits one request and reports it as a provider failure. */
    private function failOnce(InMemoryCircuitBreaker $breaker): void
    {
        $permit = $breaker->acquireExecutionPermit();
        $this->assertNotNull($permit);
        $breaker->recordFailureForPermit($permit);
    }

    /**
     * Creates a breaker whose clock reads whatever $time reference holds.
     * Use `$time = X` to advance time within the test.
     */
    private function makeBreaker(float &$time, ?CircuitBreakerPolicy $policy = null): InMemoryCircuitBreaker
    {
        return new InMemoryCircuitBreaker(
            static function () use (&$time): float {
                return $time;
            },
            $policy
        );
    }
}
