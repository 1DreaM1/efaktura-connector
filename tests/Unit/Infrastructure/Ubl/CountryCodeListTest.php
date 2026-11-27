<?php

namespace DreaM1\EfakturaConnector\Tests\Unit\Infrastructure\Ubl;

use DreaM1\EfakturaConnector\Domain\Ubl\Code\CountryCode;
use DreaM1\EfakturaConnector\Infrastructure\Ubl\CodeList\CountryCodeList;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/** Tests bundled country code list lookup behavior. */
class CountryCodeListTest extends TestCase
{
    public function testRecognizesAndResolvesPeppolCountryCodes(): void
    {
        $this->assertTrue(CountryCodeList::contains(' sk '));
        $this->assertTrue(CountryCodeList::contains('1a'));
        $this->assertFalse(CountryCodeList::contains('ZZ'));
        $this->assertFalse(CountryCodeList::contains(null));
        $this->assertSame('SK', CountryCodeList::resolve(' sk ')->value());
        $this->assertSame('1A', CountryCodeList::resolve('1a')->value());
        $this->assertNull(CountryCodeList::resolve('ZZ'));
        $this->assertNull(CountryCodeList::resolve(null));
    }

    public function testCanonicalValidationDoesNotNormalizeCountryCodes(): void
    {
        $this->assertTrue(CountryCodeList::isCanonical('SK'));
        $this->assertTrue(CountryCodeList::isCanonical('1A'));
        $this->assertFalse(CountryCodeList::isCanonical('sk'));
        $this->assertFalse(CountryCodeList::isCanonical(' SK '));
        $this->assertFalse(CountryCodeList::isCanonical('ZZ'));
        $this->assertFalse(CountryCodeList::isCanonical(null));
    }

    public function testCountryValueObjectRejectsNonCanonicalSpelling(): void
    {
        $this->expectException(InvalidArgumentException::class);
        CountryCode::fromCanonical('sk');
    }

    public function testReturnsACompleteOptionsMapWithSpecialPeppolCodes(): void
    {
        $codes   = CountryCodeList::all();
        $options = CountryCodeList::options();

        $this->assertSame($codes, array_keys($options));
        $this->assertContains('XI', $codes);
        $this->assertContains('1A', $codes);
        $this->assertNotSame('', CountryCodeList::name('XI'));
        $this->assertSame('ZZ', CountryCodeList::name('ZZ'));
    }

    public function testResolverReturnsCountryCodeValueObjects(): void
    {
        $this->assertInstanceOf(CountryCode::class, CountryCodeList::resolve('SK'));
    }
}
