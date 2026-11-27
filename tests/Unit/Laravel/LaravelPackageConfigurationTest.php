<?php

namespace DreaM1\EfakturaConnector\Tests\Unit\Laravel;

use DreaM1\EfakturaConnector\Laravel\Configuration\LaravelPackageConfiguration;
use DreaM1\EfakturaConnector\Support\Exceptions\InvalidInputException;
use PHPUnit\Framework\TestCase;

/** Covers how the Laravel package configuration is split for the core parsers. */
class LaravelPackageConfigurationTest extends TestCase
{
    public function testConnectorSettingsDropOnlyTheLaravelSection(): void
    {
        $settings = LaravelPackageConfiguration::connectorSettings([
            'base_url'       => 'https://provider.synthetic.test',
            'default_client' => 'default',
            'clients'        => [],
            'integrator_api' => [],
            'runtime'        => [],
            'laravel'        => ['cache_store' => 'redis'],
        ]);

        $this->assertSame(
            ['base_url', 'default_client', 'clients', 'integrator_api', 'runtime'],
            array_keys($settings)
        );
    }

    public function testIntegratorSettingsAreReadFromTheirSection(): void
    {
        $settings = LaravelPackageConfiguration::integratorSettings([
            'integrator_api' => ['integrator_id' => 'integrator-1'],
        ]);

        $this->assertSame(['integrator_id' => 'integrator-1'], $settings);
    }

    public function testIntegratorSettingsRejectAScalarSection(): void
    {
        $this->expectException(InvalidInputException::class);

        LaravelPackageConfiguration::integratorSettings(['integrator_api' => 'not-an-array']);
    }
}
