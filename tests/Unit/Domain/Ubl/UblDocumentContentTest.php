<?php

namespace DreaM1\EfakturaConnector\Tests\Unit\Domain\Ubl;

use DreaM1\EfakturaConnector\Domain\Ubl\UblDocumentContent;
use DreaM1\EfakturaConnector\Domain\Ubl\UblDocumentHeader;
use DreaM1\EfakturaConnector\Domain\Ubl\UblDocumentLine;
use DreaM1\EfakturaConnector\Domain\Ubl\UblDocumentParty;
use DreaM1\EfakturaConnector\Domain\Ubl\UblDocumentTaxSummary;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/** Covers UBL content hydration, normalization of optional values and rejection of unsupported roots. */
class UblDocumentContentTest extends TestCase
{
    /** The nested array form round-trips endpoint scheme/value fields without ambiguity. */
    public function testArrayFormRoundTrips(): void
    {
        $party = static function (string $name, string $endpointValue): array {
            return [
                'name'              => $name,
                'registrationId'    => null,
                'vatId'             => null,
                'taxRegistrationId' => null,
                'street'            => null,
                'buildingNumber'    => null,
                'postalCode'        => null,
                'city'              => null,
                'countryCode'       => null,
                'endpointScheme'    => '0088',
                'endpointValue'     => $endpointValue,
            ];
        };
        $stored = [
            'documentKind' => 'Invoice',
            'supplier'     => $party('Synthetic Supplier', 'supplier'),
            'customer'     => $party('Synthetic Customer', 'customer'),
            'lines'        => [[
                'id'          => '1',
                'name'        => 'Synthetic item',
                'quantity'    => '2',
                'unit'        => 'C62',
                'unitPrice'   => '10.00',
                'lineTotal'   => '20.00',
                'currency'    => 'EUR',
                'vatRate'     => '20',
                'vatCategory' => 'S',
            ]],
            'itemsTruncated' => false,
            'taxSummary'     => [[
                'category'      => 'S',
                'rate'          => '20',
                'taxableAmount' => '20.00',
                'taxAmount'     => '4.00',
            ]],
        ];

        $content = UblDocumentContent::fromArray($stored);

        $this->assertInstanceOf(UblDocumentParty::class, $content->supplier());
        $this->assertSame('supplier', $content->supplier()->endpoint()->value());
        $this->assertInstanceOf(UblDocumentLine::class, $content->lines()[0]);
        $this->assertInstanceOf(UblDocumentTaxSummary::class, $content->taxSummary()[0]);
        $this->assertSame($stored, $content->toArray());
    }

    public function testIgnoresMalformedNestedValues(): void
    {
        $content = UblDocumentContent::fromArray([
            'documentKind' => 'Invoice',
            'supplier'     => 'invalid',
            'customer'     => ['name' => ['nested']],
            'lines'        => ['invalid'],
            'taxSummary'   => null,
        ]);

        $this->assertSame('Invoice', $content->documentKind());
        $this->assertNull($content->supplier()->name());
        $this->assertNull($content->customer()->name());
        $this->assertNull($content->customer()->endpoint());
        $this->assertSame([], $content->lines());
        $this->assertSame([], $content->taxSummary());
    }

    public function testRejectsMissingOrUnsupportedDocumentKind(): void
    {
        foreach ([[], ['documentKind' => 'Order'], ['documentKind' => ['Invoice']]] as $values) {
            try {
                UblDocumentContent::fromArray($values);
            } catch (InvalidArgumentException $exception) {
                continue;
            }

            $this->fail('Malformed document content must not be restored without a supported root.');
        }
    }

    public function testCreateRejectsUnsupportedDocumentKind(): void
    {
        $this->expectException(InvalidArgumentException::class);

        UblDocumentContent::create(
            'Order',
            UblDocumentParty::fromArray([]),
            UblDocumentParty::fromArray([]),
            [],
            false,
            []
        );
    }

    public function testMalformedBooleanValuesDefaultToFalse(): void
    {
        $content = UblDocumentContent::fromArray([
            'documentKind'   => 'Invoice',
            'itemsTruncated' => 'false',
        ]);
        $header = UblDocumentHeader::fromArray(['hasPaymentTerms' => 'false']);

        $this->assertFalse($content->itemsTruncated());
        $this->assertFalse($header->hasPaymentTerms());
    }

    public function testLineScalarValuesKeepTheirExistingStringCastingBehavior(): void
    {
        $line = UblDocumentLine::fromArray([
            'id'       => 12,
            'name'     => false,
            'quantity' => null,
            'unit'     => ['invalid'],
        ]);

        $this->assertSame('12', $line->id());
        $this->assertSame('', $line->name());
        $this->assertNull($line->quantity());
        $this->assertNull($line->unit());
    }

    public function testHeaderTurnsEmptyValuesIntoNullAndRoundTrips(): void
    {
        $header = UblDocumentHeader::fromArray([
            'number'                 => 'INV-1',
            'buyerReference'         => '',
            'supplierEndpointScheme' => '0088',
            'supplierEndpointValue'  => 'sender',
            'customerEndpointScheme' => '0088',
            'customerEndpointValue'  => '',
            'hasPaymentTerms'        => true,
            'unknown'                => 'ignored',
        ]);

        $this->assertSame('INV-1', $header->number());
        $this->assertNull($header->buyerReference());
        $this->assertSame('0088:sender', $header->supplierEndpoint()->identifier());
        $this->assertNull($header->customerEndpoint());
        $this->assertTrue($header->hasPaymentTerms());
        $this->assertSame($header->toArray(), UblDocumentHeader::fromArray($header->toArray())->toArray());
        $this->assertArrayNotHasKey('unknown', $header->toArray());
    }

    public function testHeaderRoundTripsUnqualifiedEndpointWithColonInValue(): void
    {
        $header = UblDocumentHeader::fromArray([
            'supplierEndpointScheme' => null,
            'supplierEndpointValue'  => 'urn:example:sender',
        ]);

        $stored         = $header->toArray();
        $restored       = UblDocumentHeader::fromArray($stored);
        $restoredSender = $restored->supplierEndpoint();

        $this->assertArrayNotHasKey('sender', $stored);
        $this->assertNull($stored['supplierEndpointScheme']);
        $this->assertSame('urn:example:sender', $stored['supplierEndpointValue']);
        $this->assertNotNull($restoredSender);
        $this->assertNull($restoredSender->scheme());
        $this->assertSame('urn:example:sender', $restoredSender->value());
    }

    public function testPartyRoundTripsUnqualifiedEndpointWithColonInValue(): void
    {
        $party = UblDocumentParty::fromArray([
            'endpointScheme' => null,
            'endpointValue'  => 'urn:example:sender',
        ]);

        $stored           = $party->toArray();
        $restored         = UblDocumentParty::fromArray($stored);
        $restoredEndpoint = $restored->endpoint();

        $this->assertArrayNotHasKey('endpointId', $stored);
        $this->assertNull($stored['endpointScheme']);
        $this->assertSame('urn:example:sender', $stored['endpointValue']);
        $this->assertNotNull($restoredEndpoint);
        $this->assertNull($restoredEndpoint->scheme());
        $this->assertSame('urn:example:sender', $restoredEndpoint->value());
    }

    public function testPartyRequiresEndpointSchemeAndValueFields(): void
    {
        $missingEndpoint = UblDocumentParty::fromArray([]);

        $this->assertNull($missingEndpoint->endpoint());

        $explicitNullValue = UblDocumentParty::fromArray([
            'endpointScheme' => '0088',
            'endpointValue'  => null,
        ]);
        $this->assertNull($explicitNullValue->endpoint());
    }

    public function testPartyArrayDoesNotReadLegacyParticipantKeys(): void
    {
        $party = UblDocumentParty::fromArray([
            'participantScheme' => '0088',
            'participantValue'  => 'supplier',
        ]);

        $this->assertNull($party->endpoint());
        $this->assertFalse(method_exists(UblDocumentParty::class, 'participant'));
    }

    public function testHeaderExposesAllOptionalValuesAndNormalizesNotes(): void
    {
        $header = UblDocumentHeader::fromArray([
            'number'                 => 'INV-2',
            'customizationId'        => 'customization',
            'processId'              => 'process',
            'documentTypeCode'       => '380',
            'documentTypeId'         => 'invoice-type',
            'supplierEndpointScheme' => '0088',
            'supplierEndpointValue'  => 'sender',
            'customerEndpointScheme' => '0088',
            'customerEndpointValue'  => 'receiver',
            'issueDate'              => '2026-11-20',
            'dueDate'                => '2026-12-20',
            'taxPointDate'           => '2026-11-20',
            'buyerReference'         => 'buyer-ref',
            'orderReference'         => 'order-ref',
            'notes'                  => [' First note ', '', 42, ['ignored']],
            'paymentMeansCode'       => '30',
            'paymentId'              => 'payment-id',
            'hasPaymentTerms'        => true,
            'iban'                   => 'SK001234',
            'precedingInvoiceNumber' => 'INV-1',
            'currency'               => 'EUR',
            'payableAmount'          => '12.00',
            'taxExclusiveAmount'     => '10.00',
            'taxInclusiveAmount'     => '12.00',
            'lineExtensionAmount'    => '10.00',
            'taxAmount'              => '2.00',
            'roundingAmount'         => '0.00',
        ]);

        $this->assertSame('INV-2', $header->number());
        $this->assertSame('customization', $header->customizationId());
        $this->assertSame('process', $header->processId()->value());
        $this->assertSame('380', $header->documentTypeCode());
        $this->assertSame('invoice-type', $header->documentTypeId()->value());
        $this->assertSame('0088:sender', $header->supplierEndpoint()->identifier());
        $this->assertSame('0088:sender', $header->supplierEndpoint()->identifier());
        $this->assertSame('0088:receiver', $header->customerEndpoint()->identifier());
        $this->assertSame('2026-11-20', $header->issueDate()->value());
        $this->assertSame('2026-12-20', $header->dueDate()->value());
        $this->assertSame('2026-11-20', $header->taxPointDate()->value());
        $this->assertSame('buyer-ref', $header->buyerReference());
        $this->assertSame('order-ref', $header->orderReference());
        $this->assertSame('First note', $header->note());
        $this->assertSame(['First note', '42'], $header->notes());
        $this->assertSame('30', $header->paymentMeansCode());
        $this->assertSame('payment-id', $header->paymentId());
        $this->assertTrue($header->hasPaymentTerms());
        $this->assertSame('SK001234', $header->iban());
        $this->assertSame('INV-1', $header->precedingInvoiceNumber());
        $this->assertSame('EUR', $header->currency());
        $this->assertSame('12.00', $header->payableAmount());
        $this->assertSame('10.00', $header->taxExclusiveAmount());
        $this->assertSame('12.00', $header->taxInclusiveAmount());
        $this->assertSame('10.00', $header->lineExtensionAmount());
        $this->assertSame('2.00', $header->taxAmount());
        $this->assertSame('0.00', $header->roundingAmount());
        $this->assertSame([], UblDocumentHeader::fromArray(['notes' => 'not-a-list'])->notes());
        $this->assertNull(UblDocumentHeader::fromArray([])->note());
    }

    public function testUblLinePartyAndTaxSummaryExposeEveryField(): void
    {
        $line = UblDocumentLine::fromArray([
            'id'        => '1', 'name' => 'Item', 'quantity' => '2', 'unit' => 'C62',
            'unitPrice' => '5.00', 'lineTotal' => '10.00', 'currency' => 'EUR',
            'vatRate'   => '20', 'vatCategory' => 'S',
        ]);
        $this->assertSame('1', $line->id());
        $this->assertSame('Item', $line->name());
        $this->assertSame('2', $line->quantity());
        $this->assertSame('C62', $line->unit());
        $this->assertSame('5.00', $line->unitPrice());
        $this->assertSame('10.00', $line->lineTotal());
        $this->assertSame('EUR', $line->currency());
        $this->assertSame('20', $line->vatRate());
        $this->assertSame('S', $line->vatCategory());

        $party = UblDocumentParty::fromArray([
            'name'           => 'Supplier', 'endpointScheme' => '0088', 'endpointValue' => 'supplier',
            'registrationId' => '123',
            'vatId'          => 'SK123', 'taxRegistrationId' => '456', 'street' => 'Main Street',
            'buildingNumber' => '10', 'postalCode' => '81101', 'city' => 'Bratislava', 'countryCode' => 'SK',
        ]);
        $this->assertSame('Supplier', $party->name());
        $this->assertSame('0088:supplier', $party->endpoint()->identifier());
        $this->assertSame('123', $party->registrationId());
        $this->assertSame('SK123', $party->vatId());
        $this->assertSame('456', $party->taxRegistrationId());
        $this->assertSame('Main Street', $party->street());
        $this->assertSame('10', $party->buildingNumber());
        $this->assertSame('81101', $party->postalCode());
        $this->assertSame('Bratislava', $party->city());
        $this->assertSame('SK', $party->countryCode());

        $tax = UblDocumentTaxSummary::fromArray([
            'category' => 'S', 'rate' => '20', 'taxableAmount' => '10.00', 'taxAmount' => '2.00',
        ]);
        $this->assertSame('S', $tax->category());
        $this->assertSame('20', $tax->rate());
        $this->assertSame('10.00', $tax->taxableAmount());
        $this->assertSame('2.00', $tax->taxAmount());
    }
}
