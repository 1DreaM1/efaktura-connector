<?php

namespace DreaM1\EfakturaConnector\Domain\Ubl\CodeList;

use DreaM1\EfakturaConnector\Support\Exceptions\InvalidInputException;
use DreaM1\EfakturaConnector\Support\NonBlankString;
use DreaM1\EfakturaConnector\Support\StringValue;

/** Peppol DocumentTypeCode values used by cac:AdditionalDocumentReference. */
final class AdditionalDocumentTypeCode
{
    use StringValue;

    /** Code '50' identifies a project reference on a CreditNote. */
    public const PROJECT_REFERENCE = '50';

    /** Code '130' identifies an invoiced object; it is not a supporting attachment. */
    public const INVOICED_OBJECT = '130';

    /** Whether this value matches a code listed in this class. */
    public function isKnown(): bool
    {
        return in_array($this->value, [self::PROJECT_REFERENCE, self::INVOICED_OBJECT], true);
    }

    protected static function normalize(string $value): string
    {
        if (! NonBlankString::isNotBlank($value)) {
            throw new InvalidInputException('Additional document type code must not be empty.');
        }

        return $value;
    }
}
