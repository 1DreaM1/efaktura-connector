<?php

namespace DreaM1\EfakturaConnector\Support\Xml;

use DreaM1\EfakturaConnector\Support\Exceptions\InvalidInputException;

/** Checks text against the XML 1.0 Char production before it is serialized. @internal */
final class XmlCharacters
{
    /** Prevents instantiation of this static helper. */
    private function __construct()
    {
    }

    /**
     * Returns whether the text is valid UTF-8 made only of characters allowed in XML 1.0 content.
     *
     * @param  string $value Text to inspect; an empty string is valid.
     * @return bool
     */
    public static function isValid(string $value): bool
    {
        // preg_match() returns false for malformed UTF-8, which is rejected as well.
        return preg_match('/\\A[\\x{9}\\x{A}\\x{D}\\x{20}-\\x{D7FF}\\x{E000}-\\x{FFFD}\\x{10000}-\\x{10FFFF}]*\\z/u', $value) === 1;
    }

    /**
     * Throws when the text cannot be written to XML without losing or corrupting characters.
     *
     * @param  string                $value Text to inspect.
     * @param  string                $label Name of the value used in the exception message.
     * @throws InvalidInputException when the text is not valid UTF-8 or contains a character XML 1.0 forbids
     */
    public static function assertValid(string $value, string $label): void
    {
        if (! self::isValid($value)) {
            throw new InvalidInputException(
                $label . ' must be valid UTF-8 without control characters that XML 1.0 does not allow.'
            );
        }
    }
}
