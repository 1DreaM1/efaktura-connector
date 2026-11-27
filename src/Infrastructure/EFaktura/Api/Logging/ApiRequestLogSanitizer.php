<?php

namespace DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Logging;

use DOMException;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Authentication\OAuthAuthorizationScheme;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Http\Protocol\HttpHeaderLine;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Http\Protocol\HttpHeaderName;
use DreaM1\EfakturaConnector\Support\HashAlgorithm;
use DreaM1\EfakturaConnector\Support\JsonArrayDecoder;

/**
 * Sanitizes request bodies and headers before they are written to a communication log. @internal
 *
 * Header logging is an allowlist: authorization values are redacted, idempotency keys are hashed
 * for correlation, and unrecognized headers are dropped. X-Peppol-Participant-Id is deliberately kept in
 * clear text as the operation's scope (the log would otherwise not show on whose behalf a call was made);
 * the same identifier inside a request body is still redacted as a personal or business detail. Body traversal is bounded by the shared
 * item and depth limits so a large or deeply nested provider request cannot expand a log without limit.
 */
final class ApiRequestLogSanitizer
{
    /** Number of hexadecimal characters retained from a hashed idempotency key. */
    private const IDEMPOTENCY_KEY_HASH_LENGTH = 16;
    /** Request-body field whose XML content needs the dedicated UBL redaction path. */
    private const XML_FIELD_NAME = 'xml';

    private ApiPayloadLogSanitizer $payloadSanitizer;

    /**
     * Injects the sanitizer used for XML and other document payload fields.
     *
     * @param ApiPayloadLogSanitizer $payloadSanitizer Callback that removes sensitive values before logging.
     */
    public function __construct(ApiPayloadLogSanitizer $payloadSanitizer)
    {
        $this->payloadSanitizer = $payloadSanitizer;
    }

    /**
     * Decodes and redacts a serialized request body for diagnostic logging.
     *
     * @param  string|null                  $body Serialized JSON request body to sanitize, or null when absent.
     * @return array<array-key, mixed>|null
     * @throws DOMException                 when a DOM operation fails while redacting XML in the request.
     */
    public function sanitizeBody(?string $body): ?array
    {
        if ($body === null) {
            return null;
        }

        $request = JsonArrayDecoder::decodeArray($body, ApiLogSanitizationPolicy::MAX_NESTING_DEPTH);

        if ($request === null) {
            // Never log an unparsed body verbatim; retain size and hash metadata with a redacted payload marker.
            return $this->payloadSanitizer->summarizeUnparsedRequestBody($body);
        }

        return $this->sanitizeValues($request);
    }

    /**
     * Retains only allowlisted, sanitized request headers.
     *
     * @param  string[]              $headers Header lines in "Name: value" form
     * @return array<string, string>
     */
    public function sanitizeHeaders(array $headers): array
    {
        $safe = [];

        // Malformed lines and headers outside the explicit allowlist are omitted entirely.
        foreach ($headers as $header) {
            $headerLine = HttpHeaderLine::parse($header);

            if ($headerLine === null) {
                continue;
            }

            $value = $this->sanitizeHeader($headerLine);

            if ($value !== null) {
                $safe[$headerLine->name()] = $value;
            }
        }

        return $safe;
    }

    /**
     * Applies request-specific field rules through the shared bounded traversal.
     *
     * @param  array<array-key, mixed> $data Decoded request values to sanitize.
     * @return array<array-key, mixed> Sanitized values with their original keys.
     * @throws DOMException            when a DOM operation fails while redacting XML in request metadata.
     */
    private function sanitizeValues(array $data): array
    {
        return ApiLogSanitizationTraversal::sanitize(
            $data,
            $this->payloadSanitizer,
            function (string $name, $value, callable $sanitizeNested) {
                // Redact a credential-like field as a whole before inspecting its value or children.
                if ($this->isSensitiveField($name, $value)) {
                    return SensitiveDataRedactor::REDACTED_VALUE;
                }

                if (is_array($value)) {
                    return $sanitizeNested($value);
                }

                if (! is_string($value)) {
                    return $value;
                }

                return $this->isUrlField($name)
                    ? SensitiveDataRedactor::redactUrl($value)
                    : SensitiveDataRedactor::redactCredentials($value);
            }
        );
    }

    /** Determines whether a string field should receive URL-specific redaction. */
    private function isUrlField(string $name): bool
    {
        $name = strtolower(str_replace(['_', '-'], '', $name));

        return strpos($name, 'url') !== false || $name === 'uri';
    }

    /** Redacts credentials and hashes idempotency keys before logging headers. */
    private function sanitizeHeader(HttpHeaderLine $headerLine): ?string
    {
        $name = $headerLine->headerName();

        if ($name->isNamed(HttpHeaderName::AUTHORIZATION)) {
            return OAuthAuthorizationScheme::BEARER
                . ' ' . SensitiveDataRedactor::REDACTED_VALUE;
        }

        if ($name->isNamed(HttpHeaderName::IDEMPOTENCY_KEY)) {
            // A short stable digest supports correlation without retaining the original key.
            return HashAlgorithm::SHA_256 . ':'
                . substr(
                    hash(HashAlgorithm::SHA_256, $headerLine->value()),
                    0,
                    self::IDEMPOTENCY_KEY_HASH_LENGTH
                );
        }

        if (
            $name->isNamed(HttpHeaderName::PEPPOL_PARTICIPANT_ID)
            || $name->isNamed(HttpHeaderName::ACCEPT)
            || $name->isNamed(HttpHeaderName::CONTENT_TYPE)
        ) {
            return $headerLine->value();
        }

        // Fail closed for every header not explicitly classified above.
        return null;
    }

    /**
     * Detects credential and personal-data fields while allowing safe credential metadata.
     *
     * @param  string $name  Normalized request field name.
     * @param  mixed  $value Decoded field value before sanitization.
     * @return bool   Whether the whole field value should be redacted.
     */
    private function isSensitiveField(string $name, $value): bool
    {
        // Normalize common separators so access_token and access-token use the same field policy.
        $name = str_replace(['_', '-'], '', $name);

        if ($name === 'credentials' && is_array($value)) {
            // Keep the object shape, then redact sensitive child fields individually.
            return false;
        }

        if (
            in_array($name, [
            self::XML_FIELD_NAME,
            strtolower(HttpHeaderName::AUTHORIZATION),
            'value',
            ], true)
        ) {
            return true;
        }

        foreach (SensitiveFieldPolicy::fieldNameFragments() as $fragment) {
            if (strpos($name, $fragment) !== false) {
                return true;
            }
        }

        return false;
    }
}
