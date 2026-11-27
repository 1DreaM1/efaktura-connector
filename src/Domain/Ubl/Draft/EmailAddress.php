<?php

namespace DreaM1\EfakturaConnector\Domain\Ubl\Draft;

use DreaM1\EfakturaConnector\Support\Exceptions\InvalidInputException;
use DreaM1\EfakturaConnector\Support\StringValue;

/** A validated email address used by a UBL party contact. */
final class EmailAddress
{
    use StringValue;

    protected static function normalize(string $value): string
    {
        if (filter_var($value, FILTER_VALIDATE_EMAIL) === false) {
            throw new InvalidInputException('A valid email address is required.');
        }

        return $value;
    }
}
