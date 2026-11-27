<?php

namespace DreaM1\EfakturaConnector\Domain\Ubl\CodeList;

use DreaM1\EfakturaConnector\Support\Exceptions\InvalidInputException;
use DreaM1\EfakturaConnector\Support\NonBlankString;
use DreaM1\EfakturaConnector\Support\StringValue;

/** UNCL 2005 code identifying the description of a UBL period. */
final class PeriodDescriptionCode
{
    use StringValue;

    protected static function normalize(string $value): string
    {
        if (! NonBlankString::isNotBlank($value)) {
            throw new InvalidInputException('Period description code must not be empty.');
        }

        return $value;
    }
}
