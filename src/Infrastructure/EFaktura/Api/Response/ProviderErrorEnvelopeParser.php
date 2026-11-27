<?php

namespace DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Response;

use DreaM1\EfakturaConnector\Core\ProviderErrors\ProviderErrorEnvelope;
use DreaM1\EfakturaConnector\Support\JsonArrayDecoder;

/** Parses provider error bodies at the API boundary. */
final class ProviderErrorEnvelopeParser
{
    /** Returns a recognized typed envelope, or null for malformed or unrelated response bodies. */
    public static function parse(string $body): ?ProviderErrorEnvelope
    {
        $decoded = JsonArrayDecoder::decodeArray($body);

        return $decoded === null ? null : ProviderErrorEnvelope::fromArray($decoded);
    }
}
