<?php

namespace DreaM1\EfakturaConnector\Domain\Ubl\CodeList;

use DreaM1\EfakturaConnector\Support\Exceptions\InvalidInputException;
use DreaM1\EfakturaConnector\Support\NonBlankString;
use DreaM1\EfakturaConnector\Support\StringValue;

/** A coded reason for a UBL allowance or charge. */
final class AllowanceChargeReasonCode
{
    use StringValue;

    protected static function normalize(string $value): string
    {
        if (! NonBlankString::isNotBlank($value)) {
            throw new InvalidInputException('Allowance or charge reason code must not be empty.');
        }

        return $value;
    }
}
