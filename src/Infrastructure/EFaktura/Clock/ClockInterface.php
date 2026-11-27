<?php

namespace DreaM1\EfakturaConnector\Infrastructure\EFaktura\Clock;

/**
 * Source of the current wall-clock time for token expiry, retry delays and provider cooldowns.
 *
 * @api
 */
interface ClockInterface
{
    /** Returns the current Unix timestamp in seconds. */
    public function now(): int;
}
