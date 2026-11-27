<?php

namespace DreaM1\EfakturaConnector\Domain\Ubl\Draft;

use DreaM1\EfakturaConnector\Support\StringValue;

/** Invoice or credit note number stored in UBL cbc:ID. */
final class UblDocumentNumber
{
    use StringValue;

    /** Whether the number is blank under the Peppol draft preflight rule. */
    public function isBlank(): bool
    {
        return trim($this->value) === '';
    }

    protected static function normalize(string $value): string
    {
        return $value;
    }
}
