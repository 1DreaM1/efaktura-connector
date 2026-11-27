<?php

namespace DreaM1\EfakturaConnector\Tests\Laravel;

use DreaM1\EfakturaConnector\Laravel\Configuration\Internal\PackageConfigurationMerger;
use PHPUnit\Framework\TestCase;

final class LaravelPackageConfigurationMergerTest extends TestCase
{
    public function testMissingNestedValuesAreFilledFromDefaults(): void
    {
        $defaults = [
            'runtime' => ['http' => ['connect_timeout_seconds' => 10, 'request_timeout_seconds' => 45]],
            'laravel' => ['cache_store' => null],
        ];

        $merged = PackageConfigurationMerger::merge($defaults, [
            'runtime' => ['http' => ['request_timeout_seconds' => 30]],
        ]);

        $this->assertSame(10, $merged['runtime']['http']['connect_timeout_seconds']);
        $this->assertSame(30, $merged['runtime']['http']['request_timeout_seconds']);
        $this->assertSame(['cache_store' => null], $merged['laravel']);
    }

    public function testApplicationNullOverridesDefault(): void
    {
        $merged = PackageConfigurationMerger::merge(
            ['laravel' => ['cache_store' => 'shared']],
            ['laravel' => ['cache_store' => null]]
        );

        $this->assertNull($merged['laravel']['cache_store']);
    }

    public function testListsAreReplacedAndNotMergedByIndex(): void
    {
        $merged = PackageConfigurationMerger::merge(
            ['laravel' => ['validation' => ['stages' => ['A', 'B', 'C'], 'java_binary' => null]]],
            ['laravel' => ['validation' => ['stages' => ['X']]]]
        );

        $this->assertSame(['X'], $merged['laravel']['validation']['stages']);
        $this->assertArrayHasKey('java_binary', $merged['laravel']['validation']);
    }

    public function testEmptyListEmptiesTheDefaultList(): void
    {
        $merged = PackageConfigurationMerger::merge(
            ['laravel' => ['validation' => ['stages' => ['A', 'B']]]],
            ['laravel' => ['validation' => ['stages' => []]]]
        );

        $this->assertSame([], $merged['laravel']['validation']['stages']);
    }

    public function testClientProfilesAreTakenFromTheApplicationOnly(): void
    {
        $merged = PackageConfigurationMerger::merge(
            ['clients' => ['default' => ['base_url' => '', 'client_id' => '']]],
            ['clients' => ['billing' => ['base_url' => 'https://provider.synthetic.test']]]
        );

        $this->assertSame(['billing'], array_keys($merged['clients']));
        $this->assertSame(['base_url' => 'https://provider.synthetic.test'], $merged['clients']['billing']);
    }

    public function testDefaultsAreKeptWhenTheApplicationDefinesNothing(): void
    {
        $defaults = ['clients' => ['default' => []], 'runtime' => ['retry' => ['enabled' => true]]];

        $this->assertSame($defaults, PackageConfigurationMerger::merge($defaults, []));
    }

    public function testServiceMappingsAreMergedWithDefaults(): void
    {
        $merged = PackageConfigurationMerger::merge(
            ['laravel' => ['services' => []]],
            ['laravel' => ['services' => ['Contract' => 'Implementation']]]
        );

        $this->assertSame(['Contract' => 'Implementation'], $merged['laravel']['services']);
    }

    public function testUnknownApplicationKeysArePreserved(): void
    {
        $merged = PackageConfigurationMerger::merge(['a' => ['b' => 1]], ['a' => ['c' => 2], 'x' => 3]);

        $this->assertSame(['b' => 1, 'c' => 2], $merged['a']);
        $this->assertSame(3, $merged['x']);
    }
}
