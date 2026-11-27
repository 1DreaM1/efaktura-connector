<?php

namespace DreaM1\EfakturaConnector\Infrastructure\EFaktura\Clock;

/**
 * Clock backed by the system time; the default for every connector component.
 *
 * @api
 */
final class SystemClock implements ClockInterface
{
    /** Returns the current system Unix timestamp in seconds. */
    public function now(): int
    {
        return time();
    }
}
