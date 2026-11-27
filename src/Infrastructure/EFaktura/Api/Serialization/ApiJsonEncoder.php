<?php

namespace DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Serialization;

use DreaM1\EfakturaConnector\Core\Exceptions\RequestEncodingException;
use JsonException;

/** Encodes request data as compact JSON without escaping Unicode or URL characters. */
class ApiJsonEncoder
{
    /**
     * Encodes request data as JSON, throwing when the value cannot be represented.
     *
     * @param  array<array-key, mixed>  $data JSON request object or list
     * @return string
     * @throws RequestEncodingException when the value cannot be represented as JSON
     */
    public function encode(array $data): string
    {
        try {
            // Preserve readable UTF-8 and slashes, and throw so callers never mistake encoding failure for JSON text.
            return json_encode(
                $data,
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
            );
        } catch (JsonException $exception) {
            throw new RequestEncodingException('API request body could not be encoded.', $exception);
        }
    }
}
