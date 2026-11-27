<?php

namespace DreaM1\EfakturaConnector\Tests\Unit\Domain\Ubl;

use DreaM1\EfakturaConnector\Domain\Ubl\UblDocumentAddress;
use DreaM1\EfakturaConnector\Domain\Ubl\UblDocumentLine;
use DreaM1\EfakturaConnector\Domain\Ubl\UblDocumentLineAmounts;
use DreaM1\EfakturaConnector\Domain\Ubl\UblDocumentLineQuantity;
use DreaM1\EfakturaConnector\Domain\Ubl\UblDocumentLineVat;
use DreaM1\EfakturaConnector\Domain\Ubl\UblDocumentParty;
use DreaM1\EfakturaConnector\Domain\Ubl\UblDocumentPartyIdentifiers;
use PHPUnit\Framework\TestCase;

/** Party and line values are grouped, while the flat accessors and the array form stay as they were. */
final class UblDocumentPartyAndLineGroupsTest extends TestCase
{
    public function testPartyExposesItsGroupsAndTheFlatAccessors(): void
    {
        $party = UblDocumentParty::fromValues(
            'Supplier s.r.o.',
            null,
            new UblDocumentPartyIdentifiers('12345678', 'SK2022706466', '2022706466'),
            new UblDocumentAddress('Hlavná', '5', '81101', 'Bratislava', 'SK')
        );

        $this->assertSame('12345678', $party->identifiers()->registrationId());
        $this->assertSame('SK2022706466', $party->identifiers()->vatId());
        $this->assertSame('Bratislava', $party->address()->city());

        $this->assertSame('12345678', $party->registrationId());
        $this->assertSame('SK2022706466', $party->vatId());
        $this->assertSame('2022706466', $party->taxRegistrationId());
        $this->assertSame('Hlavná', $party->street());
        $this->assertSame('5', $party->buildingNumber());
        $this->assertSame('81101', $party->postalCode());
        $this->assertSame('Bratislava', $party->city());
        $this->assertSame('SK', $party->countryCode());
        $this->assertNull($party->recognizedCountryCode());
    }

    public function testPartyArrayFormRoundTrips(): void
    {
        $party = UblDocumentParty::fromValues(
            'Supplier s.r.o.',
            null,
            new UblDocumentPartyIdentifiers('12345678', null, '2022706466'),
            new UblDocumentAddress(null, null, '81101', 'Bratislava', 'SK')
        );

        $restored = UblDocumentParty::fromArray($party->toArray());

        $this->assertSame($party->toArray(), $restored->toArray());
        $this->assertSame('2022706466', $restored->identifiers()->taxRegistrationId());
        $this->assertNull($restored->address()->street());
    }

    public function testEmptyGroupsLeaveEveryValueNull(): void
    {
        $party = UblDocumentParty::fromValues(null, null, new UblDocumentPartyIdentifiers(), new UblDocumentAddress());

        foreach ($party->toArray() as $value) {
            $this->assertNull($value);
        }
    }

    public function testLineExposesItsGroupsAndTheFlatAccessors(): void
    {
        $line = UblDocumentLine::fromValues(
            '1',
            'Consulting',
            new UblDocumentLineQuantity('2', 'HUR'),
            new UblDocumentLineAmounts('50.00', '100.00', 'EUR'),
            new UblDocumentLineVat('20', 'S')
        );

        $this->assertSame('2', $line->quantityDetails()->quantity());
        $this->assertSame('100.00', $line->amounts()->lineTotal());
        $this->assertSame('S', $line->vat()->category());

        $this->assertSame('2', $line->quantity());
        $this->assertSame('HUR', $line->unit());
        $this->assertNull($line->recognizedUnitCode());
        $this->assertSame('50.00', $line->unitPrice());
        $this->assertSame('100.00', $line->lineTotal());
        $this->assertSame('EUR', $line->currency());
        $this->assertNull($line->recognizedCurrencyCode());
        $this->assertSame('20', $line->vatRate());
        $this->assertSame('S', $line->vatCategory());
    }

    public function testLineArrayFormRoundTrips(): void
    {
        $line = UblDocumentLine::fromValues(
            '1',
            'Consulting',
            new UblDocumentLineQuantity('2', 'HUR'),
            new UblDocumentLineAmounts('50.00', '100.00', 'EUR'),
            new UblDocumentLineVat('20', 'S')
        );

        $this->assertSame($line->toArray(), UblDocumentLine::fromArray($line->toArray())->toArray());
    }
}
