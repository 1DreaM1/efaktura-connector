<?php

namespace DreaM1\EfakturaConnector\Domain\Ubl;

use DreaM1\EfakturaConnector\Support\ScalarArrayValue;

/**
 * Reads values of the array form of the UBL model (toArray()/fromArray()).
 *
 * @internal
 */
final class UblArrayValue
{
    /**
     * Returns the scalar string value, or null when it is absent or not scalar.
     * @param  array<array-key, mixed> $data Array representation of a UBL domain value.
     * @param  string                  $key  Key in the UBL array representation.
     * @return string|null
     */
    public static function optionalString(array $data, string $key): ?string
    {
        return ScalarArrayValue::asString($data, $key);
    }

    /**
     * Returns the non empty string.
     * @param  array<array-key, mixed> $data Array representation of a UBL domain value.
     * @param  string                  $key  Key in the UBL array representation.
     * @return string|null
     */
    public static function nonEmptyString(array $data, string $key): ?string
    {
        $value = self::optionalString($data, $key);

        return $value === '' ? null : $value;
    }

    /**
     * Returns the stored boolean, or the default when it is absent or not a boolean.
     *
     * @param array<array-key, mixed> $data    Array representation of a UBL domain value.
     * @param string                  $key     Key in the UBL array representation.
     * @param bool                    $default Value returned when the key is absent or malformed.
     */
    public static function boolean(array $data, string $key, bool $default = false): bool
    {
        $value = $data[$key] ?? $default;

        return is_bool($value) ? $value : $default;
    }

    /**
     * Returns the array value, or an empty array when the value is absent or not an array.
     *
     * @param  array<array-key, mixed> $data Array representation of a UBL domain value.
     * @param  string                  $key  Key in the UBL array representation.
     * @return array<array-key, mixed>
     */
    public static function array(array $data, string $key): array
    {
        return is_array($data[$key] ?? null) ? $data[$key] : [];
    }

    /**
     * Restores a UBL endpoint identifier from its scheme/value representation.
     *
     * @param  array<array-key, mixed>    $data      Array representation of a UBL domain value.
     * @param  string                     $schemeKey Key identifying the code or value scheme.
     * @param  string                     $valueKey  Key used to locate the value in its source.
     * @return UblEndpointIdentifier|null
     */
    public static function endpointIdentifier(
        array $data,
        string $schemeKey,
        string $valueKey
    ): ?UblEndpointIdentifier {
        $value = self::nonEmptyString($data, $valueKey);

        if ($value === null) {
            return null;
        }

        return UblEndpointIdentifier::fromSchemeAndValue(
            ScalarArrayValue::asString($data, $schemeKey),
            $value
        );
    }
}
