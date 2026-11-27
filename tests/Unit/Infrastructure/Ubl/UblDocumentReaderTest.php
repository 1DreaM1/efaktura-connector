<?php

namespace DreaM1\EfakturaConnector\Tests\Unit\Infrastructure\Ubl;

use DreaM1\EfakturaConnector\Core\Documents\ReceivedDocumentPayload;
use DreaM1\EfakturaConnector\Core\Ubl\Exceptions\InvalidXmlException;
use DreaM1\EfakturaConnector\Core\Ubl\Exceptions\UnsupportedDocumentTypeException;
use DreaM1\EfakturaConnector\Core\Ubl\UblNamespaces;
use DreaM1\EfakturaConnector\Domain\EFaktura\Participant;
use DreaM1\EfakturaConnector\Domain\EFaktura\PeppolDocumentTypeIdentifier;
use DreaM1\EfakturaConnector\Domain\EFaktura\PeppolProcessIdentifier;
use DreaM1\EfakturaConnector\Domain\EFaktura\ProviderDocumentId;
use DreaM1\EfakturaConnector\Domain\EFaktura\ReceivedDocumentMetadata;
use DreaM1\EfakturaConnector\Domain\Ubl\PeppolBillingProfile;
use DreaM1\EfakturaConnector\Domain\Ubl\UblDocumentHeader;
use DreaM1\EfakturaConnector\Domain\Ubl\UblEndpointIdentifier;
use DreaM1\EfakturaConnector\Infrastructure\Ubl\Reader\UblDocumentReader;
use DreaM1\EfakturaConnector\Infrastructure\Ubl\Reader\UblXmlDocumentLoader;
use DreaM1\EfakturaConnector\Support\Timestamp;
use PHPUnit\Framework\TestCase;

/** Tests reading UBL invoices and credit notes. */
class UblDocumentReaderTest extends TestCase
{
    private const PARTY_DETAILS_AND_LINES = '<cac:InvoiceLine><cbc:ID>1</cbc:ID><cbc:InvoicedQuantity unitCode="C62">2</cbc:InvoicedQuantity>'
        . '<cbc:LineExtensionAmount currencyID="EUR">20.00</cbc:LineExtensionAmount>'
        . '<cac:Item><cbc:Name>Item one</cbc:Name><cac:ClassifiedTaxCategory>'
        . '<cbc:ID>S</cbc:ID><cbc:Percent>20</cbc:Percent></cac:ClassifiedTaxCategory></cac:Item>'
        . '<cac:Price><cbc:PriceAmount>10.00</cbc:PriceAmount></cac:Price></cac:InvoiceLine>'
        . '<cac:InvoiceLine><cbc:ID>2</cbc:ID><cbc:InvoicedQuantity>1</cbc:InvoicedQuantity>'
        . '<cbc:LineExtensionAmount>5.00</cbc:LineExtensionAmount>'
        . '<cac:Item><cbc:Description>Description only</cbc:Description></cac:Item></cac:InvoiceLine>';
    public function testReadsTheHeader(): void
    {
        $header = $this->reader()->read(self::invoiceXml([
            '<cac:Delivery><cbc:ActualDeliveryDate>2026-11-20</cbc:ActualDeliveryDate></cac:Delivery>',
            '<cac:PaymentTerms><cbc:Note>On receipt</cbc:Note></cac:PaymentTerms>',
        ]))
            ->header();

        $this->assertSame('INV-SYNTHETIC-1', $header->number());
        $this->assertSame(PeppolBillingProfile::CUSTOMIZATION_ID, $header->customizationId());
        $this->assertSame(PeppolBillingProfile::PROCESS_ID, $header->processId()->value());
        $this->assertSame('380', $header->documentTypeCode());
        $this->assertSame('0088:synthetic-sender', $header->supplierEndpoint()->identifier());
        $this->assertSame('synthetic-sender', $header->supplierEndpoint()->value());
        $this->assertSame('0088:synthetic-receiver', $header->customerEndpoint()->identifier());
        $this->assertSame('2026-11-10', $header->issueDate()->value());
        $this->assertSame('2026-12-10', $header->dueDate()->value());
        $this->assertSame('2026-11-20', $header->deliveryDate()->value());
        $this->assertSame('EUR', $header->currency());
        $this->assertSame('EUR', $header->recognizedCurrencyCode()->value());
        $this->assertSame('100.00', $header->payableAmount());
        $this->assertTrue($header->hasPaymentTerms());
        $this->assertSame(
            'urn:oasis:names:specification:ubl:schema:xsd:Invoice-2::Invoice##' . PeppolBillingProfile::CUSTOMIZATION_ID . '::2.1',
            $header->documentTypeId()->value()
        );
    }

    public function testReceivedPayloadXmlCanBeReadSeparatelyWithoutCheckingItsDeclaredFormat(): void
    {
        $payload = new ReceivedDocumentPayload(
            self::invoiceXml(),
            'application/json',
            $this->receivedDocumentMetadata()
        );

        $document = $this->reader()->read($payload->xml());

        $this->assertSame('Invoice', $document->kind());
        $this->assertSame('INV-SYNTHETIC-1', $document->header()->number());
    }

    public function testMissingValuesAreNull(): void
    {
        // A received document may leave out what an outgoing Peppol document requires.
        $xml = str_replace(
            [
                '<cbc:CustomizationID>' . PeppolBillingProfile::CUSTOMIZATION_ID . '</cbc:CustomizationID>',
                '<cbc:BuyerReference>SYNTHETIC-REFERENCE</cbc:BuyerReference>',
                '<cbc:DueDate>2026-12-10</cbc:DueDate>',
                '<cbc:EndpointID schemeID="0088">synthetic-receiver</cbc:EndpointID>',
            ],
            ['', '<cbc:BuyerReference>  </cbc:BuyerReference>', '', ''],
            self::invoiceXml()
        );

        $header = $this->reader()->read($xml)->header();

        $this->assertSame('INV-SYNTHETIC-1', $header->number());
        $this->assertNull($header->customizationId());
        $this->assertNull($header->documentTypeId());
        $this->assertNull($header->buyerReference());
        $this->assertNull($header->dueDate());
        $this->assertNull($header->deliveryDate());
        $this->assertNull($header->customerEndpoint());
        $this->assertNull($header->taxPointDate());
        $this->assertFalse($header->hasPaymentTerms());
    }

    public function testEndpointWithoutSchemeIsUnqualified(): void
    {
        $xml = str_replace('schemeID="0088">synthetic-sender', '>synthetic-sender', self::invoiceXml());

        $supplierEndpoint = $this->reader()->read($xml)->header()->supplierEndpoint();

        $this->assertInstanceOf(UblEndpointIdentifier::class, $supplierEndpoint);
        $this->assertNull($supplierEndpoint->scheme());
        $this->assertSame('synthetic-sender', $supplierEndpoint->identifier());
    }

    public function testEndpointPreservesRawValueAndExplicitEmptyScheme(): void
    {
        $xml = str_replace(
            '<cbc:EndpointID schemeID="0088">synthetic-sender</cbc:EndpointID>',
            '<cbc:EndpointID schemeID=""> sender </cbc:EndpointID>',
            self::invoiceXml()
        );

        $header   = $this->reader()->read($xml)->header();
        $restored = UblDocumentHeader::fromArray($header->toArray());

        $this->assertSame('', $header->supplierEndpoint()->scheme());
        $this->assertSame(' sender ', $header->supplierEndpoint()->value());
        $this->assertSame('', $restored->supplierEndpoint()->scheme());
        $this->assertSame(' sender ', $restored->supplierEndpoint()->value());
    }

    public function testEndpointWithoutSchemePreservesColonInValueWhenHeaderIsRestored(): void
    {
        $xml = str_replace(
            '<cbc:EndpointID schemeID="0088">synthetic-sender</cbc:EndpointID>',
            '<cbc:EndpointID>urn:example:synthetic-sender</cbc:EndpointID>',
            self::invoiceXml()
        );

        $header   = $this->reader()->read($xml)->header();
        $restored = UblDocumentHeader::fromArray($header->toArray());

        $this->assertNull($header->supplierEndpoint()->scheme());
        $this->assertSame('urn:example:synthetic-sender', $header->supplierEndpoint()->value());
        $this->assertNull($restored->supplierEndpoint()->scheme());
        $this->assertSame('urn:example:synthetic-sender', $restored->supplierEndpoint()->value());
    }

    public function testCreditNoteDueDateComesFromPaymentMeans(): void
    {
        $header = $this->reader()->read(self::creditNoteXml())->header();

        $this->assertSame('381', $header->documentTypeCode());
        $this->assertSame('2026-12-12', $header->dueDate()->value());
        $this->assertSame('VS-1', $header->paymentId());
        $this->assertSame('ORIGINAL-1', $header->precedingInvoiceNumber());
    }

    public function testReadsPartiesLinesAndTaxSummary(): void
    {
        $document = $this->reader()->read(self::invoiceXml([self::PARTY_DETAILS_AND_LINES]));
        $content  = $document->content();
        $supplier = $content->supplier();

        $this->assertSame('Invoice', $document->kind());
        $this->assertSame('Synthetic Supplier', $supplier->name());
        $this->assertSame('0088:synthetic-sender', $supplier->endpoint()->identifier());
        $this->assertSame('12345678', $supplier->registrationId());
        $this->assertSame('SK2012345676', $supplier->vatId());
        $this->assertSame('2012345676', $supplier->taxRegistrationId());
        $this->assertSame('Main Street', $supplier->street());
        $this->assertSame('SK', $supplier->countryCode());
        $this->assertSame('SK', $supplier->recognizedCountryCode()->value());
        $this->assertSame('Synthetic Customer', $content->customer()->name());
        $this->assertCount(2, $content->lines());
        $this->assertSame('Item one', $content->lines()[0]->name());
        $this->assertSame('2', $content->lines()[0]->quantity());
        $this->assertSame('C62', $content->lines()[0]->unit());
        $this->assertSame('C62', $content->lines()[0]->recognizedUnitCode()->value());
        $this->assertSame('EUR', $content->lines()[0]->currency());
        $this->assertSame('EUR', $content->lines()[0]->recognizedCurrencyCode()->value());
        $this->assertSame('S', $content->lines()[0]->vatCategory());
        $this->assertSame('Description only', $content->lines()[1]->name());
        $this->assertSame(
            'EUR',
            $content->lines()[1]->currency(),
            'A line without currencyID uses the document currency.'
        );
        $this->assertSame('S', $content->taxSummary()[0]->category());
        $this->assertSame('20.00', $content->taxSummary()[0]->taxableAmount());
        $this->assertTrue($content->hasTaxCategory('S'));
        $this->assertFalse($content->hasTaxCategory('O'));
    }

    public function testPreservesUnknownCodeTextWithoutResolvingIt(): void
    {
        $xml = str_replace(
            [
                '<cbc:DocumentCurrencyCode>EUR</cbc:DocumentCurrencyCode>',
                'currencyID="EUR"',
                '<cbc:IdentificationCode>SK</cbc:IdentificationCode>',
                'unitCode="C62"',
            ],
            [
                '<cbc:DocumentCurrencyCode>ZZZ</cbc:DocumentCurrencyCode>',
                'currencyID="ZZZ"',
                '<cbc:IdentificationCode>ZZ</cbc:IdentificationCode>',
                'unitCode="ZZZ"',
            ],
            self::invoiceXml([self::PARTY_DETAILS_AND_LINES])
        );

        $document = $this->reader()->read($xml);
        $header   = $document->header();
        $supplier = $document->content()->supplier();
        $line     = $document->content()->lines()[0];

        $this->assertSame('ZZZ', $header->currency());
        $this->assertNull($header->recognizedCurrencyCode());
        $this->assertSame('ZZ', $supplier->countryCode());
        $this->assertNull($supplier->recognizedCountryCode());
        $this->assertSame('ZZZ', $line->unit());
        $this->assertNull($line->recognizedUnitCode());
        $this->assertSame('ZZZ', $line->currency());
        $this->assertNull($line->recognizedCurrencyCode());
    }

    public function testDoesNotResolveNonCanonicalUnitLabelsAndPreservesTheOriginalXmlValue(): void
    {
        $xml  = str_replace('unitCode="C62"', 'unitCode="ks"', self::invoiceXml([self::PARTY_DETAILS_AND_LINES]));
        $line = $this->reader()->read($xml)->content()->lines()[0];

        $this->assertSame('ks', $line->unit());
        $this->assertNull($line->recognizedUnitCode());
    }

    public function testDicPartyIdentificationIsTheTaxRegistrationNotTheRegistrationId(): void
    {
        $party = '<cac:AccountingSupplierParty><cac:Party>'
            . '<cac:PartyIdentification><cbc:ID schemeID="0245">2012345676</cbc:ID></cac:PartyIdentification>'
            . '<cac:PartyIdentification><cbc:ID>12345678</cbc:ID></cac:PartyIdentification>'
            . '</cac:Party></cac:AccountingSupplierParty>';
        $xml = preg_replace(
            '#<cac:AccountingSupplierParty>.*?</cac:AccountingSupplierParty>#',
            $party,
            self::invoiceXml()
        );

        $supplier = $this->reader()->read($xml)->content()->supplier();

        $this->assertSame('12345678', $supplier->registrationId());
        $this->assertSame('2012345676', $supplier->taxRegistrationId());
        $this->assertNull($supplier->endpoint());
    }

    public function testReadingPartyDoesNotRequireTaxIdentifiers(): void
    {
        $party = '<cac:AccountingSupplierParty><cac:Party>'
            . '<cbc:EndpointID schemeID="0088">synthetic-sender</cbc:EndpointID>'
            . '<cac:PartyLegalEntity><cbc:RegistrationName>Synthetic Supplier</cbc:RegistrationName>'
            . '</cac:PartyLegalEntity></cac:Party></cac:AccountingSupplierParty>';
        $xml = preg_replace(
            '#<cac:AccountingSupplierParty>.*?</cac:AccountingSupplierParty>#',
            $party,
            self::invoiceXml()
        );

        $supplier = $this->reader()->read($xml)->content()->supplier();

        $this->assertNull($supplier->vatId());
        $this->assertNull($supplier->taxRegistrationId());
        $this->assertNull($supplier->registrationId());
    }

    public function testReadsCreditedQuantityOfCreditNoteLines(): void
    {
        $content = $this->reader()->read(self::creditNoteXml())->content();

        $this->assertSame('CreditNote', $content->documentKind());
        $this->assertSame('5', $content->lines()[0]->quantity());
    }

    public function testRejectsUnsafeXml(): void
    {
        $this->expectException(InvalidXmlException::class);

        $this->reader()->read(
            '<!DOCTYPE Invoice [<!ENTITY data SYSTEM "file:///synthetic">]><Invoice>&data;</Invoice>'
        );
    }

    public function testRejectsOtherDocuments(): void
    {
        $this->expectException(UnsupportedDocumentTypeException::class);

        $this->reader()->read('<Order xmlns="urn:oasis:names:specification:ubl:schema:xsd:Order-2"/>');
    }

    private function reader(): UblDocumentReader
    {
        return new UblDocumentReader(new UblXmlDocumentLoader());
    }

    private function receivedDocumentMetadata(): ReceivedDocumentMetadata
    {
        return ReceivedDocumentMetadata::create(
            ProviderDocumentId::fromString('provider-doc-1'),
            Participant::parse('0088:synthetic-sender'),
            Participant::parse('0088:synthetic-receiver'),
            PeppolDocumentTypeIdentifier::fromString('synthetic-document-type'),
            PeppolProcessIdentifier::fromString('synthetic-process'),
            Timestamp::fromIso8601('2026-11-20T14:30:00Z')
        );
    }

    /** @param string[] $extraElements appended after the parties */
    private static function invoiceXml(array $extraElements = []): string
    {
        $lines    = implode('', $extraElements);
        $hasLines = strpos($lines, 'InvoiceLine') !== false;

        return '<Invoice xmlns="urn:oasis:names:specification:ubl:schema:xsd:Invoice-2"'
            . ' xmlns:cac="' . UblNamespaces::COMMON_AGGREGATE_COMPONENTS . '" xmlns:cbc="' . UblNamespaces::COMMON_BASIC_COMPONENTS . '">'
            . '<cbc:CustomizationID>' . PeppolBillingProfile::CUSTOMIZATION_ID . '</cbc:CustomizationID>'
            . '<cbc:ProfileID>' . PeppolBillingProfile::PROCESS_ID . '</cbc:ProfileID>'
            . '<cbc:ID>INV-SYNTHETIC-1</cbc:ID><cbc:IssueDate>2026-11-10</cbc:IssueDate>'
            . '<cbc:DueDate>2026-12-10</cbc:DueDate>'
            . '<cbc:InvoiceTypeCode>380</cbc:InvoiceTypeCode><cbc:DocumentCurrencyCode>EUR</cbc:DocumentCurrencyCode>'
            . '<cbc:BuyerReference>SYNTHETIC-REFERENCE</cbc:BuyerReference>'
            . '<cac:AccountingSupplierParty><cac:Party><cbc:EndpointID schemeID="0088">synthetic-sender</cbc:EndpointID>'
            . '<cac:PostalAddress><cbc:StreetName>Main Street</cbc:StreetName>'
            . '<cac:Country><cbc:IdentificationCode>SK</cbc:IdentificationCode></cac:Country></cac:PostalAddress>'
            . '<cac:PartyTaxScheme><cbc:CompanyID>SK2012345676</cbc:CompanyID><cac:TaxScheme><cbc:ID>VAT</cbc:ID></cac:TaxScheme></cac:PartyTaxScheme>'
            . '<cac:PartyTaxScheme><cbc:CompanyID>2012345676</cbc:CompanyID><cac:TaxScheme><cbc:ID>TAX</cbc:ID></cac:TaxScheme></cac:PartyTaxScheme>'
            . '<cac:PartyLegalEntity><cbc:RegistrationName>Synthetic Supplier</cbc:RegistrationName>'
            . '<cbc:CompanyID>12345678</cbc:CompanyID></cac:PartyLegalEntity>'
            . '</cac:Party></cac:AccountingSupplierParty>'
            . '<cac:AccountingCustomerParty><cac:Party><cbc:EndpointID schemeID="0088">synthetic-receiver</cbc:EndpointID>'
            . '<cac:PartyName><cbc:Name>Synthetic Customer</cbc:Name></cac:PartyName>'
            . '</cac:Party></cac:AccountingCustomerParty>'
            . ($hasLines ? '' : $lines)
            . '<cac:TaxTotal><cbc:TaxAmount currencyID="EUR">4.00</cbc:TaxAmount><cac:TaxSubtotal>'
            . '<cbc:TaxableAmount>20.00</cbc:TaxableAmount><cbc:TaxAmount>4.00</cbc:TaxAmount>'
            . '<cac:TaxCategory><cbc:ID>S</cbc:ID><cbc:Percent>20</cbc:Percent></cac:TaxCategory></cac:TaxSubtotal></cac:TaxTotal>'
            . '<cac:LegalMonetaryTotal><cbc:PayableAmount>100.00</cbc:PayableAmount></cac:LegalMonetaryTotal>'
            . ($hasLines ? $lines : '')
            . '</Invoice>';
    }

    private static function creditNoteXml(): string
    {
        return '<CreditNote xmlns="urn:oasis:names:specification:ubl:schema:xsd:CreditNote-2"'
            . ' xmlns:cac="' . UblNamespaces::COMMON_AGGREGATE_COMPONENTS . '" xmlns:cbc="' . UblNamespaces::COMMON_BASIC_COMPONENTS . '">'
            . '<cbc:ID>CN-SYNTHETIC-1</cbc:ID><cbc:CreditNoteTypeCode>381</cbc:CreditNoteTypeCode>'
            . '<cac:BillingReference><cac:InvoiceDocumentReference><cbc:ID>ORIGINAL-1</cbc:ID></cac:InvoiceDocumentReference></cac:BillingReference>'
            . '<cac:PaymentMeans><cbc:PaymentMeansCode>30</cbc:PaymentMeansCode><cbc:PaymentDueDate>2026-12-12</cbc:PaymentDueDate>'
            . '<cbc:PaymentID>VS-1</cbc:PaymentID></cac:PaymentMeans>'
            . '<cac:CreditNoteLine><cbc:ID>1</cbc:ID><cbc:CreditedQuantity unitCode="C62">5</cbc:CreditedQuantity>'
            . '<cbc:LineExtensionAmount currencyID="EUR">50.00</cbc:LineExtensionAmount>'
            . '<cac:Item><cbc:Name>Credited item</cbc:Name></cac:Item></cac:CreditNoteLine>'
            . '</CreditNote>';
    }
}
