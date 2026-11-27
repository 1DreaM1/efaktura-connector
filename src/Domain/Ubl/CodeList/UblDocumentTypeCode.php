<?php

namespace DreaM1\EfakturaConnector\Domain\Ubl\CodeList;

use DreaM1\EfakturaConnector\Support\Exceptions\InvalidInputException;
use DreaM1\EfakturaConnector\Support\NonBlankString;
use DreaM1\EfakturaConnector\Support\StringValue;

/** Type code written as cbc:InvoiceTypeCode or cbc:CreditNoteTypeCode. */
final class UblDocumentTypeCode
{
    use StringValue;

    /** Whether this value matches a code listed in DocumentTypeCode. */
    public function isKnown(): bool
    {
        return in_array($this->value, DocumentTypeCode::all(), true);
    }

    protected static function normalize(string $value): string
    {
        if (! NonBlankString::isNotBlank($value)) {
            throw new InvalidInputException('UBL document type code must not be empty.');
        }

        return $value;
    }
}
