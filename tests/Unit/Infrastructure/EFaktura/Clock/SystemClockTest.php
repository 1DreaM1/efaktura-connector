<?php

namespace DreaM1\EfakturaConnector\Tests\Unit\Infrastructure\EFaktura\Clock;

use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Clock\SystemClock;
use DreaM1\EfakturaConnector\Tests\Support\FrozenClock;
use PHPUnit\Framework\TestCase;

/** Tests the system clock and the deterministic test clock used by time-dependent components. */
class SystemClockTest extends TestCase
{
    public function testSystemClockReturnsTheCurrentUnixTimestamp(): void
    {
        $before = time();
        $now    = (new SystemClock())->now();
        $after  = time();

        $this->assertGreaterThanOrEqual($before, $now);
        $this->assertLessThanOrEqual($after, $now);
    }

    public function testFrozenClockAdvancesOnlyExplicitly(): void
    {
        $clock = new FrozenClock();

        $this->assertSame(FrozenClock::EPOCH, $clock->now());

        $clock->advance(90);

        $this->assertSame(FrozenClock::EPOCH + 90, $clock->now());
    }
}
