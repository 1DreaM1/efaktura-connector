<?php

namespace DreaM1\EfakturaConnector\Core\Documents;

use DreaM1\EfakturaConnector\Support\Exceptions\InvalidInputException;
use DreaM1\EfakturaConnector\Support\StringValue;

/**
 * Stable UUID that identifies one logical idempotent API operation.
 *
 * The key is stored in lower case, so the same UUID written in upper or lower case yields the same
 * Idempotency-Key header and is recognized by the provider as a repeat of the same operation.
 */
final class IdempotencyKey
{
    use StringValue;

    /** Pattern for an accepted hyphenated UUID idempotency key. */
    private const UUID_PATTERN = '/\A[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}\z/i';

    protected static function normalize(string $value): string
    {
        if (preg_match(self::UUID_PATTERN, $value) !== 1) {
            throw new InvalidInputException('Idempotency key must be a UUID.');
        }

        return strtolower($value);
    }
}
