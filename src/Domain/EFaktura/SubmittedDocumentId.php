<?php

namespace DreaM1\EfakturaConnector\Domain\EFaktura;

use DreaM1\EfakturaConnector\Support\Exceptions\InvalidInputException;
use DreaM1\EfakturaConnector\Support\NonBlankString;
use DreaM1\EfakturaConnector\Support\StringValue;

/** Opaque identifier assigned by the sender to a document submission. */
final class SubmittedDocumentId
{
    use StringValue;

    protected static function normalize(string $value): string
    {
        NonBlankString::assertNotBlank($value, 'Submitted document ID must not be blank.');

        return $value;
    }
}
