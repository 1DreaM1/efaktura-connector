<?php

namespace DreaM1\EfakturaConnector\Domain\Ubl\CodeList;

use DreaM1\EfakturaConnector\Support\Exceptions\InvalidInputException;
use DreaM1\EfakturaConnector\Support\NonBlankString;
use DreaM1\EfakturaConnector\Support\StringValue;

/** Tax scheme identifiers (cac:TaxScheme/cbc:ID). */
final class TaxSchemeCode
{
    use StringValue;

    /** Tax scheme code 'VAT' for value added tax. */
    public const VAT = 'VAT';
    /** Any tax registration other than VAT, e.g. the Slovak DIČ. */
    public const OTHER = 'TAX';

    /** Whether this value matches a code listed in this class. */
    public function isKnown(): bool
    {
        return in_array($this->value, [self::VAT, self::OTHER], true);
    }

    protected static function normalize(string $value): string
    {
        if (! NonBlankString::isNotBlank($value)) {
            throw new InvalidInputException('Tax scheme code must not be empty.');
        }

        return $value;
    }
}
