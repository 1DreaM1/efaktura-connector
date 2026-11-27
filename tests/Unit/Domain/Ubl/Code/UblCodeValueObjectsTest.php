<?php

namespace DreaM1\EfakturaConnector\Tests\Unit\Domain\Ubl\Code;

use DreaM1\EfakturaConnector\Domain\Ubl\Code\CountryCode;
use DreaM1\EfakturaConnector\Domain\Ubl\Code\CurrencyCode;
use DreaM1\EfakturaConnector\Domain\Ubl\Code\UnitCode;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/** Checks canonical UBL country, currency and unit code values. */
class UblCodeValueObjectsTest extends TestCase
{
    public function testCreatesCanonicalCodes(): void
    {
        $this->assertSame('SK', CountryCode::fromCanonical('SK')->value());
        $this->assertSame('1A', CountryCode::fromCanonical('1A')->value());
        $this->assertSame('EUR', CurrencyCode::fromCanonical('EUR')->value());
        $this->assertSame('C62', UnitCode::fromCanonical('C62')->value());
    }

    public function testCountryCodeRejectsNonCanonicalValues(): void
    {
        $this->expectException(InvalidArgumentException::class);
        CountryCode::fromCanonical('sk');
    }

    public function testCurrencyCodeRejectsNonCanonicalValues(): void
    {
        $this->expectException(InvalidArgumentException::class);
        CurrencyCode::fromCanonical('EURO');
    }

    public function testUnitCodeRejectsNonCanonicalValues(): void
    {
        $this->expectException(InvalidArgumentException::class);
        UnitCode::fromCanonical('kgm ');
    }
}
