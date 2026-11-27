<?php

namespace DreaM1\EfakturaConnector\Domain\EFaktura;

use DreaM1\EfakturaConnector\Support\Exceptions\InvalidInputException;
use DreaM1\EfakturaConnector\Support\NonBlankString;
use DreaM1\EfakturaConnector\Support\StringValue;

/** Immutable UBL CustomizationID used to identify the document specification. */
final class PeppolCustomizationIdentifier
{
    use StringValue;

    protected static function normalize(string $value): string
    {
        NonBlankString::assertNotBlank($value, 'Peppol customization identifier must not be blank.');

        return $value;
    }
}
