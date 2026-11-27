<?php

namespace DreaM1\EfakturaConnector\Tests\Unit\Support;

use DreaM1\EfakturaConnector\Support\ConfigurationValues;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/** Tests configuration value parsing and validation helpers. */
class ConfigurationValuesTest extends TestCase
{
    public function testSectionDefaultsToAnEmptyArrayAndRejectsOtherValues(): void
    {
        $this->assertSame([], ConfigurationValues::section([], 'runtime', 'runtime'));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('runtime configuration section');
        ConfigurationValues::section(['runtime' => 'invalid'], 'runtime', 'runtime');
    }

    /**
     * Parses integer values and decimal integer strings without changing them.
     *
     * @param mixed $value    Input value to parse.
     * @param int   $expected Expected integer result.
     * @dataProvider integerProvider
     */
    public function testIntegerReadsIntegersAndDecimalStrings($value, int $expected): void
    {
        $this->assertSame($expected, ConfigurationValues::integer(['limit' => $value], 'limit', 10, 'runtime.limit'));
    }

    /**
     * Provides integer values and their expected parsed values.
     *
     * @return array<string, array{mixed, int}> Input and expected result cases.
     */
    public function integerProvider(): array
    {
        return [
            'integer'          => [25, 25],
            'integer string'   => [' 25 ', 25],
            'negative integer' => [-2, -2],
        ];
    }

    /**
     * Parses environment-style values into booleans.
     *
     * @param mixed $value    Input value to parse.
     * @param bool  $expected Expected boolean result.
     * @dataProvider booleanProvider
     */
    public function testBooleanAcceptsLaravelEnvironmentValues($value, bool $expected): void
    {
        $this->assertSame($expected, ConfigurationValues::boolean($value, 'laravel.feature'));
    }

    /**
     * Provides Laravel-style boolean values and their expected results.
     *
     * @return array<string, array{mixed, bool}> Input and expected result cases.
     */
    public function booleanProvider(): array
    {
        return [
            'true'            => [true, true],
            'false'           => [false, false],
            'enabled string'  => ['on', true],
            'disabled string' => ['off', false],
            'one string'      => ['1', true],
            'zero integer'    => [0, false],
        ];
    }

    public function testAmbiguousBooleanValuesAreRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('laravel.feature configuration must be a boolean');

        ConfigurationValues::boolean('sometimes', 'laravel.feature');
    }
}
