<?php

namespace DreaM1\EfakturaConnector\Tests\Unit\Domain;

use DreaM1\EfakturaConnector\Core\Ubl\PeppolDocumentTypeIdentifierBuilder;
use DreaM1\EfakturaConnector\Domain\EFaktura\PeppolDocumentTypeIdentifier;
use DreaM1\EfakturaConnector\Domain\EFaktura\PeppolProcessIdentifier;
use DreaM1\EfakturaConnector\Domain\Ubl\PeppolBillingProfile;
use DreaM1\EfakturaConnector\Domain\Ubl\UblDocumentRoot;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/** Covers validation and value semantics for Peppol document-type and process identifiers. */
class PeppolIdentifierValueObjectsTest extends TestCase
{
    public function testDocumentTypeIdentifierPreservesItsValueAndComparesByValue(): void
    {
        $identifier = PeppolDocumentTypeIdentifier::fromString('urn:example:invoice-type');

        $this->assertSame('urn:example:invoice-type', $identifier->value());
        $this->assertTrue($identifier->equals(PeppolDocumentTypeIdentifier::fromString('urn:example:invoice-type')));
        $this->assertFalse($identifier->equals(PeppolDocumentTypeIdentifier::fromString('urn:example:credit-note-type')));
    }

    public function testProcessIdentifierPreservesItsValueAndComparesByValue(): void
    {
        $identifier = PeppolProcessIdentifier::fromString('urn:example:billing-process');

        $this->assertSame('urn:example:billing-process', $identifier->value());
        $this->assertTrue($identifier->equals(PeppolProcessIdentifier::fromString('urn:example:billing-process')));
        $this->assertFalse($identifier->equals(PeppolProcessIdentifier::fromString('urn:example:other-process')));
    }

    public function testBothIdentifiersRejectBlankValues(): void
    {
        $this->expectException(InvalidArgumentException::class);
        PeppolDocumentTypeIdentifier::fromString('  ');
    }

    public function testProcessIdentifierRejectsBlankValue(): void
    {
        $this->expectException(InvalidArgumentException::class);
        PeppolProcessIdentifier::fromString('  ');
    }

    public function testUblDocumentTypeBuilderReturnsTypedIdentifier(): void
    {
        $identifier = PeppolDocumentTypeIdentifierBuilder::forDocument(
            UblDocumentRoot::INVOICE,
            PeppolBillingProfile::CUSTOMIZATION_ID
        );

        $this->assertInstanceOf(PeppolDocumentTypeIdentifier::class, $identifier);
    }

    public function testUblDocumentTypeBuilderRejectsUnsupportedRoot(): void
    {
        $this->expectException(InvalidArgumentException::class);

        PeppolDocumentTypeIdentifierBuilder::forDocument('Order');
    }
}
