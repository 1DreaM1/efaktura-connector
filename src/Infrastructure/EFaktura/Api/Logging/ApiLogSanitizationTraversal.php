<?php

namespace DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Logging;

use DOMException;
use DreaM1\EfakturaConnector\Support\JsonArrayDecoder;

/** Applies shared payload handling and bounded traversal to API log values. @internal */
final class ApiLogSanitizationTraversal
{
    /**
     * Sanitizes a nested value tree with a caller-supplied policy for non-payload fields.
     *
     * The callback receives the normalized field name, its value, and a function for recursively
     * sanitizing nested arrays. This keeps request and response field policies separate while
     * sharing payload handling and traversal limits.
     *
     * @param  array<array-key, mixed>                                                                             $data             Nested request or response values to sanitize.
     * @param  ApiPayloadLogSanitizer                                                                              $payloadSanitizer Sanitizer for XML and other document payload values.
     * @param  callable(string, mixed, callable(array<array-key, mixed>): (array<array-key, mixed>|string)): mixed $sanitizeEntry
     *                                                                                                                               Receives a normalized field name, its value and a bounded nested-array sanitizer.
     * @return array<array-key, mixed>
     * @throws DOMException                                                                                        when a DOM operation fails while traversing or redacting XML.
     */
    public static function sanitize(
        array $data,
        ApiPayloadLogSanitizer $payloadSanitizer,
        callable $sanitizeEntry
    ): array {
        return self::sanitizeAtDepth($data, $payloadSanitizer, $sanitizeEntry, 0);
    }

    /**
     * @param  array<array-key, mixed>                                                                             $data             Nested request or response values at the current depth.
     * @param  ApiPayloadLogSanitizer                                                                              $payloadSanitizer Sanitizer for XML and other document payload values.
     * @param  callable(string, mixed, callable(array<array-key, mixed>): (array<array-key, mixed>|string)): mixed $sanitizeEntry
     *                                                                                                                               Receives a normalized field name, its value and a bounded nested-array sanitizer.
     * @param  int                                                                                                 $depth            Current nested-array depth; the root array is at depth zero.
     * @return array<array-key, mixed>
     * @throws DOMException                                                                                        when a DOM operation fails while traversing or redacting XML.
     */
    private static function sanitizeAtDepth(
        array $data,
        ApiPayloadLogSanitizer $payloadSanitizer,
        callable $sanitizeEntry,
        int $depth
    ): array {
        $safe   = [];
        $isList = JsonArrayDecoder::isList($data);
        // Bound retained entries while preserving safe keys for stable log structure.
        $isTruncated = count($data) > ApiLogSanitizationPolicy::MAX_ARRAY_ITEMS;
        $itemLimit   = $isTruncated
            ? ApiLogSanitizationPolicy::MAX_ARRAY_ITEMS - 1
            : ApiLogSanitizationPolicy::MAX_ARRAY_ITEMS;

        foreach (array_slice($data, 0, $itemLimit, true) as $key => $value) {
            $name    = strtolower((string) $key);
            $safeKey = self::safeArrayKey($key, $safe, $isList);

            if (self::isPayloadField($name)) {
                // Payloads need XML-aware redaction instead of the field-level scalar policy.
                $safe[$safeKey] = $payloadSanitizer->sanitize($value);
                continue;
            }

            $sanitizeNested = static function (array $nested) use (
                $payloadSanitizer,
                $sanitizeEntry,
                $depth
            ) {
                if ($depth >= ApiLogSanitizationPolicy::MAX_NESTING_DEPTH) {
                    // Replace deeper subtrees with a marker to bound recursion and log size.
                    return ApiLogSanitizationPolicy::TRUNCATED_VALUE;
                }

                return self::sanitizeAtDepth($nested, $payloadSanitizer, $sanitizeEntry, $depth + 1);
            };

            $safe[$safeKey] = $sanitizeEntry($name, $value, $sanitizeNested);
        }

        if ($isTruncated) {
            if ($isList) {
                $safe[] = ApiLogSanitizationPolicy::TRUNCATED_VALUE;
            } else {
                $markerKey = ApiLogSanitizationPolicy::TRUNCATION_MARKER_KEY;
                while (array_key_exists($markerKey, $safe)) {
                    $markerKey .= '_';
                }

                $safe[$markerKey] = ApiLogSanitizationPolicy::TRUNCATED_VALUE;
            }
        }

        return $safe;
    }

    /**
     * Replaces dynamic or malformed object keys so sensitive data cannot leak through field names.
     *
     * @param  int|string              $key       Original array key.
     * @param  array<array-key, mixed> $sanitized Keys already retained at this level.
     * @param  bool                    $isList    Whether numeric keys are list indexes.
     * @return int|string
     */
    private static function safeArrayKey($key, array $sanitized, bool $isList)
    {
        if (is_int($key) && $isList) {
            return $key;
        }

        if (
            is_string($key)
            && ApiLogValuePolicy::matches($key, ApiLogValuePolicy::SAFE_FIELD_NAME_PATTERN, 64)
        ) {
            return $key;
        }

        $safeKey = SensitiveDataRedactor::REDACTED_VALUE;
        while (array_key_exists($safeKey, $sanitized)) {
            $safeKey .= '_';
        }

        return $safeKey;
    }

    /** Identifies fields whose values need XML or payload-specific redaction. */
    private static function isPayloadField(string $name): bool
    {
        return $name === 'payload'
            || $name === 'content';
    }
}
