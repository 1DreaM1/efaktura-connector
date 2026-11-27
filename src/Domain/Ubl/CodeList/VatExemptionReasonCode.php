<?php

namespace DreaM1\EfakturaConnector\Domain\Ubl\CodeList;

use DreaM1\EfakturaConnector\Support\Exceptions\InvalidInputException;
use DreaM1\EfakturaConnector\Support\NonBlankString;
use DreaM1\EfakturaConnector\Support\StringValue;

/** CEF VATEX VAT exemption reason codes (cbc:TaxExemptionReasonCode). */
final class VatExemptionReasonCode
{
    use StringValue;

    /** VAT exemption reason code 'VATEX-EU-O' for a transaction not subject to VAT. */
    public const NOT_SUBJECT_TO_VAT = 'VATEX-EU-O';

    /** Whether this value matches a code listed in this class. */
    public function isKnown(): bool
    {
        return $this->value === self::NOT_SUBJECT_TO_VAT;
    }

    protected static function normalize(string $value): string
    {
        if (! NonBlankString::isNotBlank($value)) {
            throw new InvalidInputException('VAT exemption reason code must not be empty.');
        }

        return $value;
    }
}
