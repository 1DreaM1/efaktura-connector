<?php

namespace DreaM1\EfakturaConnector\Core\Documents;

use Exception;

/** Creates UUID idempotency keys accepted by DataHosting API operations. */
final class IdempotencyKeyGenerator
{
    /**
     * Generates a random RFC 4122 version 4 UUID.
     *
     * Create one key for a logical operation and retain it for retries of that operation.
     *
     * @return IdempotencyKey
     * @throws Exception      when a secure random source is unavailable
     */
    public static function generate(): IdempotencyKey
    {
        $bytes    = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);

        $hex = bin2hex($bytes);

        return IdempotencyKey::fromString(
            substr($hex, 0, 8) . '-'
            . substr($hex, 8, 4) . '-'
            . substr($hex, 12, 4) . '-'
            . substr($hex, 16, 4) . '-'
            . substr($hex, 20, 12)
        );
    }
}
