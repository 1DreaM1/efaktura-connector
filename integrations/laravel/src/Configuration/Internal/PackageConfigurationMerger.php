<?php

namespace DreaM1\EfakturaConnector\Laravel\Configuration\Internal;

/**
 * Merges the application's connector configuration over the package defaults.
 *
 * Laravel's own `mergeConfigFrom()` only merges top-level keys, so a published file that omits a nested
 * value would silently lose the package default (including its `env()` lookup). This merger fills missing
 * nested keys from the defaults. Lists (`laravel.validation.stages`) and the named `clients` profiles are
 * taken from the application unchanged, because they describe a complete set chosen by the host.
 *
 * @internal
 */
final class PackageConfigurationMerger
{
    /** Top-level keys whose application value replaces the default entirely instead of being merged. */
    private const REPLACED_KEYS = ['clients'];

    /**
     * Returns the package defaults overlaid with the application values.
     *
     * @param  array<string, mixed> $defaults    Package default configuration.
     * @param  array<string, mixed> $application Configuration already loaded by the application.
     * @return array<string, mixed>
     */
    public static function merge(array $defaults, array $application): array
    {
        $merged = $defaults;

        foreach ($application as $key => $value) {
            if (in_array($key, self::REPLACED_KEYS, true) || !array_key_exists($key, $defaults)) {
                $merged[$key] = $value;

                continue;
            }

            $merged[$key] = self::mergeValue($defaults[$key], $value);
        }

        return $merged;
    }

    /**
     * Merges one value: associative arrays recursively, everything else by replacement.
     *
     * @param  mixed $default
     * @param  mixed $value
     * @return mixed
     */
    private static function mergeValue($default, $value)
    {
        if (!is_array($default) || !is_array($value) || self::isList($default) || self::isList($value)) {
            return $value;
        }

        $merged = $default;

        foreach ($value as $key => $item) {
            $merged[$key] = array_key_exists($key, $default) ? self::mergeValue($default[$key], $item) : $item;
        }

        return $merged;
    }

    /**
     * Whether the array is a non-empty list with keys 0..n-1 (an empty array is treated as a map).
     *
     * @param array<mixed> $value
     */
    private static function isList(array $value): bool
    {
        return $value !== [] && array_keys($value) === range(0, count($value) - 1);
    }
}
