<?php

namespace DreaM1\EfakturaConnector\Support;

use stdClass;

/** Decodes JSON values and checks the shape of decoded objects and lists. */
final class JsonArrayDecoder
{
    /** Default maximum nesting depth passed to PHP's JSON decoder. */
    private const DEFAULT_MAX_DEPTH = 512;

    /**
     * Whether a decoded array has the contiguous zero-based keys used by JSON lists.
     *
     * @param  mixed $value Decoded JSON value.
     * @return bool
     */
    public static function isList($value): bool
    {
        return is_array($value) && ($value === [] || array_keys($value) === range(0, count($value) - 1));
    }

    /**
     * Returns an associative representation of one decoded JSON object.
     *
     * Empty stdClass objects are valid. Empty PHP arrays and sequential arrays are treated as lists,
     * while non-list associative arrays are accepted for callers that already decoded objects as arrays.
     *
     * @param  mixed                        $value Decoded JSON value.
     * @return array<array-key, mixed>|null
     */
    public static function objectAsArray($value): ?array
    {
        if ($value instanceof stdClass) {
            return (array) $value;
        }

        if (! is_array($value) || self::isList($value)) {
            return null;
        }

        return $value;
    }

    /**
     * Returns a PHP array for a JSON object or list, or null for invalid JSON or a scalar root.
     * @noinspection JsonEncodingApiUsageInspection
     * @param  string                       $json  JSON text to decode.
     * @param  int                          $depth Maximum JSON nesting depth.
     * @return array<array-key, mixed>|null
     */
    public static function decodeArray(string $json, int $depth = self::DEFAULT_MAX_DEPTH): ?array
    {
        if ($depth < 1) {
            return null;
        }

        $decoded = json_decode($json, true, $depth);

        // A valid JSON scalar or null is still the wrong root shape for API response objects/lists.
        return is_array($decoded) ? $decoded : null;
    }

    /**
     * Returns a PHP list for a JSON array, or null for invalid JSON or any other root value.
     * Nested JSON objects remain stdClass values so callers can distinguish objects from lists.
     *
     * @param  string           $json  JSON text to decode.
     * @param  int              $depth Maximum JSON nesting depth.
     * @return list<mixed>|null
     */
    public static function decodeList(string $json, int $depth = self::DEFAULT_MAX_DEPTH): ?array
    {
        $decoded = self::decodePreservingJsonTypes($json, $depth);

        // JSON objects decode to stdClass here, so an array is always a JSON list.
        return is_array($decoded) ? array_values($decoded) : null;
    }

    /**
     * Returns an associative representation of a JSON object, or null for any other root value.
     * Nested objects remain stdClass values so callers can distinguish them from JSON lists.
     *
     * @param  string                       $json  JSON text to decode.
     * @param  int                          $depth Maximum JSON nesting depth.
     * @return array<array-key, mixed>|null
     */
    public static function decodeObject(string $json, int $depth = self::DEFAULT_MAX_DEPTH): ?array
    {
        $decoded = self::decodePreservingJsonTypes($json, $depth);

        if (! $decoded instanceof stdClass) {
            return null;
        }

        return self::normalizeObjectProperties(get_object_vars($decoded));
    }

    /**
     * Decodes JSON without converting JSON objects to associative arrays.
     *
     * @return mixed
     */
    private static function decodePreservingJsonTypes(string $json, int $depth)
    {
        if ($depth < 1) {
            return null;
        }

        return json_decode($json, false, $depth);
    }

    /**
     * @param  array<array-key, mixed> $properties Decoded properties; numeric property names may become integer keys.
     * @return array<array-key, mixed>
     */
    private static function normalizeObjectProperties(array $properties): array
    {
        foreach ($properties as $key => $value) {
            $properties[$key] = self::normalizeValue($value);
        }

        return $properties;
    }

    /**
     * @param  mixed $value
     * @return mixed
     */
    private static function normalizeValue($value)
    {
        if ($value instanceof stdClass) {
            // Keep objects intact, including empty objects and objects with numeric property names.
            return $value;
        }

        if (is_array($value)) {
            foreach ($value as $key => $item) {
                $value[$key] = self::normalizeValue($item);
            }
        }

        return $value;
    }
}
