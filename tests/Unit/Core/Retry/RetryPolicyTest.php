<?php

namespace DreaM1\EfakturaConnector\Tests\Unit\Core\Retry;

use DreaM1\EfakturaConnector\Core\Retry\RetryPolicy;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/** Covers retry attempt bounds, exponential backoff, delay caps and overflow-safe calculations. */
class RetryPolicyTest extends TestCase
{
    public function testDefaultPolicyHasThreeAttempts(): void
    {
        $this->assertSame(3, RetryPolicy::default()->maxAttempts());
    }

    public function testRetryAttemptsCannotExceedTheConfiguredSafetyLimit(): void
    {
        $this->assertSame(RetryPolicy::MAX_ATTEMPTS, (new RetryPolicy(RetryPolicy::MAX_ATTEMPTS, 0, 0))->maxAttempts());

        $this->expectException(InvalidArgumentException::class);
        new RetryPolicy(RetryPolicy::MAX_ATTEMPTS + 1, 0, 0);
    }

    public function testExponentialDelayDoublesEachRetry(): void
    {
        $policy = new RetryPolicy(5, 500, 60_000);

        $this->assertSame(500, $policy->delayForRetry(1));
        $this->assertSame(1000, $policy->delayForRetry(2));
        $this->assertSame(2000, $policy->delayForRetry(3));
        $this->assertSame(4000, $policy->delayForRetry(4));
    }

    public function testDelayIsCappedAtMaxDelay(): void
    {
        $policy = new RetryPolicy(10, 500, 1000);

        $this->assertSame(1000, $policy->delayForRetry(5));
        $this->assertSame(1000, $policy->delayForRetry(10));
    }

    public function testMaxDelayNeverExceeded(): void
    {
        $policy = new RetryPolicy(RetryPolicy::MAX_ATTEMPTS, 500, 30_000);

        for ($i = 1; $i <= RetryPolicy::MAX_ATTEMPTS; $i++) {
            $this->assertLessThanOrEqual(30_000, $policy->delayForRetry($i));
        }
    }

    public function testCapsVeryLargeRetryNumbersWithoutIntegerOverflow(): void
    {
        $maxSafeDelay = intdiv(PHP_INT_MAX, 1000);
        $policy       = new RetryPolicy(1, 500, $maxSafeDelay);

        $this->assertSame($maxSafeDelay, $policy->delayForRetry(PHP_INT_MAX));
    }

    public function testZeroDelayRemainsZeroEvenForAnExtremeRetryNumber(): void
    {
        $policy = new RetryPolicy(1, 0, intdiv(PHP_INT_MAX, 1000));

        $this->assertSame(0, $policy->delayForRetry(PHP_INT_MAX));
    }

    public function testRejectsMaximumDelayThatWouldOverflowUsleepConversion(): void
    {
        $tooLargeDelay = intdiv(PHP_INT_MAX, 1000) + 1;

        $this->expectException(InvalidArgumentException::class);
        new RetryPolicy(1, 0, $tooLargeDelay);
    }

    public function testRejectsInvalidPolicyAndRetryNumbers(): void
    {
        foreach (
            [
                static function (): void {
                    new RetryPolicy(0, 500, 30_000);
                },
                static function (): void {
                    new RetryPolicy(3, -1, 30_000);
                },
                static function (): void {
                    new RetryPolicy(3, 500, -1);
                },
                static function (): void {
                    (new RetryPolicy(3, 500, 30_000))->delayForRetry(0);
                },
            ] as $invalidCase
        ) {
            try {
                $invalidCase();
                $this->fail('Invalid retry values must be rejected.');
            } catch (InvalidArgumentException $exception) {
                $this->assertNotSame('', $exception->getMessage());
            }
        }
    }
}
