<?php

namespace DreaM1\EfakturaConnector\Support;

/** Reads scalar string values from arrays without applying domain normalization. */
final class ScalarArrayValue
{
    /**
     * Returns a scalar array field as a string, using the default for absent or null values.
     * Returns null when the resulting value is non-scalar.
     *
     * @param array<array-key, mixed> $data    Array containing the value to convert.
     * @param string                  $key     Key to read from the array.
     * @param mixed                   $default Value used when the field is absent or null
     */
    public static function asString(array $data, string $key, $default = null): ?string
    {
        $value = $data[$key] ?? $default;

        return is_scalar($value) ? (string) $value : null;
    }
}
