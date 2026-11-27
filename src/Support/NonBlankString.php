<?php

namespace DreaM1\EfakturaConnector\Support;

use DreaM1\EfakturaConnector\Support\Exceptions\InvalidInputException;

/** Shared validation for strings that must contain non-whitespace characters. */
final class NonBlankString
{
    /**
     * Returns whether the value contains at least one non-whitespace character.
     *
     * @param  string $value String to inspect.
     * @return bool
     */
    public static function isNotBlank(string $value): bool
    {
        return preg_match('/\\A[\\s\\p{Z}]*\\z/u', $value) !== 1;
    }

    /**
     * Throws with the caller's message when a required string contains only whitespace.
     *
     * @param  string                $value   String to validate.
     * @param  string                $message Exception message to use when validation fails.
     * @throws InvalidInputException when the value contains only whitespace
     */
    public static function assertNotBlank(string $value, string $message): void
    {
        if (! self::isNotBlank($value)) {
            throw new InvalidInputException($message);
        }
    }
}
