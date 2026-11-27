<?php

namespace DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Logging;

use DOMException;
use DreaM1\EfakturaConnector\Support\HashAlgorithm;

/** Summarizes or redacts payload values included in communication logs. @internal */
final class ApiPayloadLogSanitizer
{
    /**
     * Redacts non-string values or returns a bounded summary of a string payload.
     *
     * @param  mixed                       $value Value to parse, normalize, or validate.
     * @return array<string, mixed>|string
     * @throws DOMException                when a DOM operation fails while redacting an XML payload.
     */
    public function sanitize($value)
    {
        return is_string($value)
            ? $this->summary($value)
            : SensitiveDataRedactor::REDACTED_VALUE;
    }

    /**
     * Summarizes an undecodable request body without retaining its content.
     *
     * @param  string               $body Raw request body that could not be decoded as JSON.
     * @return array<string, mixed>
     */
    public function summarizeUnparsedRequestBody(string $body): array
    {
        // Keep diagnostic size and a stable fingerprint, but never copy malformed or non-JSON content into logs.
        return [
            'unparsed'      => true,
            'payload'       => SensitiveDataRedactor::REDACTED_VALUE,
            'bytes'         => strlen($body),
            'payloadSha256' => hash(HashAlgorithm::SHA_256, $body),
        ];
    }

    /** @return array<string, mixed>
     * @throws DOMException when a DOM operation fails while redacting an XML payload.
     */
    private function summary(string $payload): array
    {
        // Keep a fingerprint of the original bytes for correlation; the content field contains only redacted XML.
        return [
            'redacted' => true,
            'bytes'    => strlen($payload),
            'sha256'   => hash(HashAlgorithm::SHA_256, $payload),
            'content'  => UblPayloadRedactor::redact($payload),
        ];
    }
}
