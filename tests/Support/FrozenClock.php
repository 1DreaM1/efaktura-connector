<?php

namespace DreaM1\EfakturaConnector\Tests\Support;

use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Clock\ClockInterface;

/** Deterministic clock for tests that advances only when told to. */
final class FrozenClock implements ClockInterface
{
    /** Arbitrary fixed Unix timestamp (2027-01-15T08:00:00Z) used as a stable test epoch. */
    public const EPOCH = 1_800_000_000;

    private int $now;

    public function __construct(int $now = self::EPOCH)
    {
        $this->now = $now;
    }

    public function now(): int
    {
        return $this->now;
    }

    public function advance(int $seconds): void
    {
        $this->now += $seconds;
    }
}
