<?php

namespace DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Authentication;

use DreaM1\EfakturaConnector\Support\Exceptions\InvalidInputException;

/** @api A non-negative OAuth token lifetime measured in seconds. */
final class OAuthTokenLifetime
{
    private int $seconds;

    private function __construct(int $seconds)
    {
        if ($seconds < 0) {
            throw new InvalidInputException('OAuth token lifetime must not be negative.');
        }

        $this->seconds = $seconds;
    }

    /** Creates a token lifetime from its provider or cache representation. */
    public static function fromSeconds(int $seconds): self
    {
        return new self($seconds);
    }

    /** Returns the lifetime in seconds for expiry calculations and serialization. */
    public function seconds(): int
    {
        return $this->seconds;
    }
}
