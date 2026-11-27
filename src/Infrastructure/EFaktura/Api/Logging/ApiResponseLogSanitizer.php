<?php

namespace DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Logging;

use DOMException;
use DreaM1\EfakturaConnector\Core\Retry\RetryAfter;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Authentication\OAuthAuthorizationScheme;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Response\DocumentMetadataFields;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Http\Protocol\HttpHeaderName;
use DreaM1\EfakturaConnector\Support\Timestamp;

/**
 * Sanitizes provider response bodies and headers before they are logged. @internal
 *
 * Values are retained only for known fields with expected formats; unknown fields and values are
 * redacted by default. Nested arrays are traversed only within the shared item and depth limits.
 */
final class ApiResponseLogSanitizer
{
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
     * Redacts unsafe fields from a decoded provider response body.
     *
     * @param  array<array-key, mixed> $data Decoded provider response body to sanitize.
     * @return array<array-key, mixed>
     * @throws DOMException            when a DOM operation fails while redacting XML in the response.
     */
    public function sanitizeBody(array $data): array
    {
        return $this->sanitizeValues($data);
    }

    /**
     * Redacts unsafe values from decoded provider response headers.
     *
     * @param  array<array-key, mixed> $headers Decoded provider response headers to sanitize.
     * @return array<array-key, mixed>
     * @throws DOMException            when a DOM operation fails while redacting XML in response metadata.
     */
    public function sanitizeHeaders(array $headers): array
    {
        return $this->sanitizeValues($headers);
    }

    /**
     * Applies response-specific allowlists through the shared bounded traversal.
     *
     * @param  array<array-key, mixed> $data Decoded response values to sanitize.
     * @return array<array-key, mixed> Sanitized values with their original keys.
     * @throws DOMException            when a DOM operation fails while redacting XML in response headers.
     */
    private function sanitizeValues(array $data): array
    {
        return ApiLogSanitizationTraversal::sanitize(
            $data,
            $this->payloadSanitizer,
            function (string $name, $value, callable $sanitizeNested) {
                if (is_array($value)) {
                    return $sanitizeNested($value);
                }

                // Keep nulls and validated allowlisted values; redact unknown scalar fields by default.
                return $value === null || $this->isSafeValue($name, $value)
                    ? $value
                    : SensitiveDataRedactor::REDACTED_VALUE;
            }
        );
    }

    /**
     * Allows only scalar response values with a known safe shape into logs.
     *
     * @param  string $name  Normalized response field name.
     * @param  mixed  $value Decoded response value.
     * @return bool   Whether the scalar value matches an allowlisted shape.
     */
    private function isSafeValue(string $name, $value): bool
    {
        if ($name === 'unparsed') {
            return is_bool($value);
        }

        if (
            in_array($name, [
            'bytes',
            'expires_in',
            ], true)
        ) {
            return is_int($value) && $value >= 0;
        }

        if (
            in_array($name, [
            'valid',
            'success',
            'accepted',
            ], true)
        ) {
            return is_bool($value);
        }

        if ($name === 'receivedat') {
            return is_string($value) && Timestamp::isValidIso8601($value);
        }

        if ($name === 'token_type') {
            return is_string($value) && strcasecmp(
                $value,
                OAuthAuthorizationScheme::BEARER
            ) === 0;
        }

        if ($name === strtolower(HttpHeaderName::RETRY_AFTER)) {
            return is_string($value)
                && trim($value) === $value
                && RetryAfter::deadline([RetryAfter::HEADER_NAME => $value]) !== null;
        }

        return $this->isSafeString($name, $value);
    }

    /**
     * Applies field-specific character allowlists before retaining response strings.
     *
     * @param  string $name  Normalized response field name.
     * @param  mixed  $value Candidate response value.
     * @return bool   Whether the value is a string matching that field's safe pattern.
     */
    private function isSafeString(string $name, $value): bool
    {
        /** @var array<string, string>|null $patterns Field names mapped to regular expressions for safe values. */
        static $patterns = null;
        $patterns ??= [
            'identifier' => ApiLogValuePolicy::SAFE_IDENTIFIER_PATTERN,
            'messageid'  => ApiLogValuePolicy::SAFE_IDENTIFIER_PATTERN,
            strtolower(DocumentMetadataFields::SENDER_PARTICIPANT_ID)
                => ApiLogValuePolicy::PARTICIPANT_IDENTIFIER_PATTERN,
            strtolower(DocumentMetadataFields::RECEIVER_PARTICIPANT_ID)
                                              => ApiLogValuePolicy::PARTICIPANT_IDENTIFIER_PATTERN,
            strtolower(DocumentMetadataFields::DOCUMENT_TYPE_ID)
                                               => ApiLogValuePolicy::SAFE_TECHNICAL_IDENTIFIER_PATTERN,
            'responsecode'                     => ApiLogValuePolicy::SAFE_PROVIDER_CODE_PATTERN,
            'responsedescription'              => '/\A[\p{L}\p{N} ._-]+\z/u',
            'status'                           => ApiLogValuePolicy::SAFE_PROVIDER_CODE_PATTERN,
            'category'                         => ApiLogValuePolicy::SAFE_PROVIDER_CODE_PATTERN,
            strtolower(HttpHeaderName::CONTENT_TYPE)
                => '/\A[a-z0-9.+-]+\/[a-z0-9.+-]+(?:;\s*charset=[a-z0-9._-]+)?\z/i',
        ];

        return isset($patterns[$name]) && ApiLogValuePolicy::matches($value, $patterns[$name]);
    }
}
