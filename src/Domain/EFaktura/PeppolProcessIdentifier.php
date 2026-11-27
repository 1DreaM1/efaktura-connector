<?php

namespace DreaM1\EfakturaConnector\Domain\EFaktura;

use DreaM1\EfakturaConnector\Support\Exceptions\InvalidInputException;
use DreaM1\EfakturaConnector\Support\NonBlankString;
use DreaM1\EfakturaConnector\Support\StringValue;

/** Immutable Peppol business process identifier. */
final class PeppolProcessIdentifier
{
    use StringValue;

    protected static function normalize(string $value): string
    {
        NonBlankString::assertNotBlank($value, 'Peppol process identifier must not be blank.');

        return $value;
    }
}
