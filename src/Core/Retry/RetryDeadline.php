<?php

namespace DreaM1\EfakturaConnector\Core\Retry;

/** Absolute Unix time after which a request may be retried. */
final class RetryDeadline
{
    private int $unixTimestamp;

    private function __construct(int $unixTimestamp)
    {
        $this->unixTimestamp = $unixTimestamp;
    }

    /** Creates a deadline from a Unix timestamp in seconds. */
    public static function fromUnixTimestamp(int $unixTimestamp): self
    {
        return new self($unixTimestamp);
    }

    /** Returns the absolute Unix timestamp in seconds. */
    public function unixTimestamp(): int
    {
        return $this->unixTimestamp;
    }

    /** Returns whether this deadline is later than the supplied Unix timestamp. */
    public function isAfter(int $unixTimestamp): bool
    {
        return $this->unixTimestamp > $unixTimestamp;
    }
}
