<?php

namespace DreaM1\EfakturaConnector\Laravel\Configuration;

use DreaM1\EfakturaConnector\Support\ConfigurationValues;
use DreaM1\EfakturaConnector\Support\Exceptions\InvalidInputException;

/**
 * @internal Splits the Laravel package configuration into the parts consumed by the typed configuration parsers.
 *
 * The package array holds the connector sections (`default_client`, `clients`, `integrator_api`,
 * `runtime`) and the Laravel-only `laravel` section. Keeping the split here means no caller has to know which
 * key the core parsers refuse to see.
 */
final class LaravelPackageConfiguration
{
    private function __construct()
    {
    }

    /**
     * Returns the connector settings: the package configuration without the Laravel-only section.
     *
     * @param  array<string, mixed> $packageConfiguration Complete package configuration.
     * @return array<string, mixed>
     */
    public static function connectorSettings(array $packageConfiguration): array
    {
        unset($packageConfiguration['laravel']);

        return $packageConfiguration;
    }

    /**
     * Returns the `integrator_api` section of the package configuration.
     *
     * @param  array<string, mixed>    $packageConfiguration Complete package configuration.
     * @return array<array-key, mixed>
     * @throws InvalidInputException   when the section is not an array
     */
    public static function integratorSettings(array $packageConfiguration): array
    {
        return ConfigurationValues::section($packageConfiguration, 'integrator_api', 'integrator_api');
    }
}
