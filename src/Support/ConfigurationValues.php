<?php

namespace DreaM1\EfakturaConnector\Support;

use DreaM1\EfakturaConnector\Support\Exceptions\InvalidInputException;

/** Reads and validates common values from nested configuration arrays. */
final class ConfigurationValues
{
    /**
     * Rejects keys that are not part of the documented configuration schema.
     *
     * @param  array<array-key, mixed> $configuration Configuration map to inspect.
     * @param  string[]                $allowed       Supported keys for the map.
     * @param  string                  $path          Full configuration path used in validation errors.
     * @throws InvalidInputException   when an unsupported key is present.
     */
    public static function assertAllowedKeys(array $configuration, array $allowed, string $path): void
    {
        foreach (array_keys($configuration) as $key) {
            $normalizedKey = (string) $key;

            if (! in_array($normalizedKey, $allowed, true)) {
                throw new InvalidInputException(
                    'Unknown ' . $path . ' configuration key "' . $normalizedKey . '".'
                );
            }
        }
    }

    /**
     * Returns a configuration section, defaulting an absent or null section to an empty array.
     *
     * @param  array<array-key, mixed> $configuration Configuration map to read.
     * @param  string                  $key           Section key.
     * @param  string                  $path          Full section path used in validation errors.
     * @return array<array-key, mixed>
     * @throws InvalidInputException   when the section is not an array
     */
    public static function section(array $configuration, string $key, string $path): array
    {
        $value = $configuration[$key] ?? [];

        if (! is_array($value)) {
            throw new InvalidInputException('The ' . $path . ' configuration section must be an array.');
        }

        return $value;
    }

    /**
     * Reads an integer setting, accepting integers and decimal integer strings.
     *
     * @param  array<array-key, mixed> $configuration Configuration map containing the setting.
     * @param  string                  $key           Setting key.
     * @param  int                     $default       Value used when the setting is absent or null.
     * @param  string                  $path          Full setting path used in validation errors.
     * @return int
     * @throws InvalidInputException   when the value is not an integer or integer string
     */
    public static function integer(array $configuration, string $key, int $default, string $path): int
    {
        $value = $configuration[$key] ?? $default;

        if (is_int($value)) {
            return $value;
        }

        if (is_string($value)) {
            $value = trim($value);

            if ($value !== '' && preg_match('/^-?\d+$/D', $value) === 1) {
                // The pattern enforces integer syntax; filter_var then rejects values outside PHP's integer range.
                $integer = filter_var($value, FILTER_VALIDATE_INT);

                if ($integer !== false) {
                    return $integer;
                }
            }
        }

        throw new InvalidInputException('The ' . $path . ' configuration must be an integer.');
    }

    /**
     * Accepts native booleans and common values returned by environment helpers.
     *
     * @param  mixed                 $value Value to convert to a boolean.
     * @param  string                $path  Full setting path used in validation errors.
     * @return bool
     * @throws InvalidInputException when the value is not a supported boolean representation
     */
    public static function boolean($value, string $path): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if ($value === 1 || $value === 0) {
            return (bool) $value;
        }

        if (is_string($value)) {
            $normalized = strtolower(trim($value));

            if (in_array($normalized, ['1', 'true', 'on'], true)) {
                return true;
            }

            if (in_array($normalized, ['0', 'false', 'off'], true)) {
                return false;
            }
        }

        throw new InvalidInputException('The ' . $path . ' configuration must be a boolean.');
    }
}
