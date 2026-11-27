<?php

namespace DreaM1\EfakturaConnector\Domain\EFaktura;

use DreaM1\EfakturaConnector\Support\Exceptions\InvalidInputException;
use DreaM1\EfakturaConnector\Support\NonBlankString;
use DreaM1\EfakturaConnector\Support\StringValue;

/** Opaque message identifier assigned by an eFaktura provider. */
final class ProviderMessageId
{
    use StringValue;

    protected static function normalize(string $value): string
    {
        if (! NonBlankString::isNotBlank($value)) {
            throw new InvalidInputException('Provider message ID must not be empty.');
        }

        return $value;
    }
}
