<?php

namespace DreaM1\EfakturaConnector\Domain\EFaktura;

use DreaM1\EfakturaConnector\Support\Exceptions\InvalidInputException;
use DreaM1\EfakturaConnector\Support\NonBlankString;
use DreaM1\EfakturaConnector\Support\StringValue;

/** Opaque identifier assigned to one Peppol message-level status record. */
final class MessageLevelStatusIdentifier
{
    use StringValue;

    protected static function normalize(string $value): string
    {
        if (! NonBlankString::isNotBlank($value)) {
            throw new InvalidInputException('Message-level status identifier must not be empty.');
        }

        return $value;
    }
}
