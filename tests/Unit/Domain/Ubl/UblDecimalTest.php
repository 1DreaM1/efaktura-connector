<?php

namespace DreaM1\EfakturaConnector\Tests\Unit\Domain\Ubl;

use DreaM1\EfakturaConnector\Domain\Ubl\Draft\UblDecimal;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/** Checks exact decimal formatting, minor-unit conversion, scale limits and overflow handling. */
class UblDecimalTest extends TestCase
{
    public function testFormatsMinorUnitsExactlyForPositiveAndNegativeAmounts(): void
    {
        $this->assertSame('123.45', UblDecimal::fromMinorUnits(12345)->value());
        $this->assertSame('-123.45', UblDecimal::fromMinorUnits(-12345)->value());
        $this->assertSame('-0.05', UblDecimal::fromMinorUnits(-5)->value());
        $this->assertSame('12345', UblDecimal::fromMinorUnits(12345, 0)->value());
    }

    public function testFormatsFloatWithFixedScaleAndDecimalPoint(): void
    {
        $this->assertSame('12.35', UblDecimal::fromFloat(12.345, 2)->value());
        $this->assertSame('-1.200', UblDecimal::fromFloat(-1.2, 3)->value());
    }

    public function testFromStringTrimsSurroundingWhitespaceAndPreservesDecimalDigits(): void
    {
        $this->assertSame('00123.4500', UblDecimal::fromString(" \t00123.4500\n")->value());
    }

    public function testFromStringRejectsWhitespaceWithoutADecimalValue(): void
    {
        $this->expectException(InvalidArgumentException::class);

        UblDecimal::fromString(" \t\n ");
    }

    public function testRejectsNegativeScales(): void
    {
        foreach (
            [
            static function (): void {
                UblDecimal::fromMinorUnits(100, -1);
            },
            static function (): void {
                UblDecimal::fromFloat(1.0, -1);
            },
            ] as $formatter
        ) {
            try {
                $formatter();
                $this->fail('Negative decimal scales must be rejected.');
            } catch (InvalidArgumentException $exception) {
                $this->assertNotSame('', $exception->getMessage());
            }
        }
    }

    public function testFormatsNativeIntegerBoundariesWithoutOverflow(): void
    {
        $minimumDigits = substr((string) PHP_INT_MIN, 1);
        $expected      = '-' . substr($minimumDigits, 0, -2) . '.' . substr($minimumDigits, -2);

        $this->assertSame($expected, UblDecimal::fromMinorUnits(PHP_INT_MIN)->value());
        $this->assertSame((string) PHP_INT_MAX, UblDecimal::fromMinorUnits(PHP_INT_MAX, 0)->value());
        $this->assertSame('0.0000000000000000001', UblDecimal::fromMinorUnits(1, 19)->value());
    }

    public function testRejectsNonFiniteAmountsAndUnreasonablyLargeScales(): void
    {
        foreach ([INF, -INF, NAN] as $value) {
            try {
                UblDecimal::fromFloat($value, 2);
                $this->fail('Non-finite values are not valid UBL decimals.');
            } catch (InvalidArgumentException $exception) {
                $this->assertNotSame('', $exception->getMessage());
            }
        }

        foreach (
            [
            static function (): void {
                UblDecimal::fromMinorUnits(1, 1001);
            },
            static function (): void {
                UblDecimal::fromFloat(1.0, 1001);
            },
            ] as $formatter
        ) {
            try {
                $formatter();
                $this->fail('Excessive scales must be rejected before allocating a huge value.');
            } catch (InvalidArgumentException $exception) {
                $this->assertNotSame('', $exception->getMessage());
            }
        }
    }
}
