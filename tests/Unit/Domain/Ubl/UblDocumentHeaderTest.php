<?php

namespace DreaM1\EfakturaConnector\Tests\Unit\Domain\Ubl;

use DreaM1\EfakturaConnector\Domain\Ubl\UblDocumentHeader;
use DreaM1\EfakturaConnector\Domain\Ubl\UblDocumentRoot;
use PHPUnit\Framework\TestCase;

/** Checks UBL header hydration, endpoint scheme boundaries, dates and removal of legacy aliases. */
class UblDocumentHeaderTest extends TestCase
{
    /**
     * Preserves endpoint values and scheme boundaries across array round trips.
     *
     * @param array<string, mixed> $values             Endpoint representation to parse.
     * @param string|null          $expectedScheme     Expected scheme identifier.
     * @param string               $expectedValue      Expected endpoint value.
     * @param string               $expectedIdentifier Expected serialized endpoint identifier.
     * @dataProvider endpointValues
     */
    public function testEndpointArrayFormsRoundTripWithoutLosingSchemeBoundaries(
        array $values,
        ?string $expectedScheme,
        string $expectedValue,
        string $expectedIdentifier
    ): void {
        $header   = UblDocumentHeader::fromArray($values);
        $restored = UblDocumentHeader::fromArray($header->toArray());

        $this->assertNotNull($restored->supplierEndpoint());
        $this->assertSame($expectedScheme, $restored->supplierEndpoint()->scheme());
        $this->assertSame($expectedValue, $restored->supplierEndpoint()->value());
        $this->assertSame($expectedIdentifier, $restored->supplierEndpoint()->identifier());
    }

    /**
     * Provides endpoint array representations and their expected round-trip values.
     *
     * @return array<array-key, array<array-key, mixed>> Endpoint representation cases.
     */
    public function endpointValues(): array
    {
        return [
            'qualified endpoint with colons in value' => [[
                'supplierEndpointScheme' => '0088',
                'supplierEndpointValue'  => 'urn:example:sender:42',
            ], '0088', 'urn:example:sender:42', '0088:urn:example:sender:42'],
            'unqualified endpoint with colon in value' => [[
                'supplierEndpointScheme' => null,
                'supplierEndpointValue'  => 'urn:example:sender:42',
            ], null, 'urn:example:sender:42', 'urn:example:sender:42'],
        ];
    }

    public function testMalformedEndpointAndDocumentValuesAreIgnored(): void
    {
        $explicit = UblDocumentHeader::fromArray([
            'supplierEndpointScheme' => '0245',
            'supplierEndpointValue'  => 'current-sender',
            'customerEndpointScheme' => '0088',
            'customerEndpointValue'  => '',
        ]);

        $this->assertSame('0245:current-sender', $explicit->supplierEndpoint()->identifier());
        $this->assertNull($explicit->customerEndpoint());

        $malformed = UblDocumentHeader::fromArray([
            'supplierEndpointValue' => ['nested'],
            'customerEndpointValue' => ['nested'],
            'number'                => ['unexpected'],
            'issueDate'             => 20261120,
            'notes'                 => 'not-a-list',
        ]);

        $this->assertNull($malformed->supplierEndpoint());
        $this->assertNull($malformed->customerEndpoint());
        $this->assertNull($malformed->number());
        $this->assertNull($malformed->issueDate());
        $this->assertSame('20261120', $malformed->toArray()['issueDate']);
        $this->assertSame([], $malformed->notes());
        $this->assertSame($malformed->toArray(), UblDocumentHeader::fromArray($malformed->toArray())->toArray());
    }

    public function testDocumentRootIsPreservedAndUnsupportedRootsAreIgnored(): void
    {
        $header = UblDocumentHeader::fromArray([
            'documentRoot' => UblDocumentRoot::CREDIT_NOTE,
        ]);
        $restored  = UblDocumentHeader::fromArray($header->toArray());
        $malformed = UblDocumentHeader::fromArray(['documentRoot' => 'Order']);

        $this->assertSame(UblDocumentRoot::CREDIT_NOTE, $header->documentRoot()->value());
        $this->assertSame(UblDocumentRoot::CREDIT_NOTE, $restored->documentRoot()->value());
        $this->assertNull($malformed->documentRoot());
    }

    public function testDoesNotReadLegacyEndpointKeysOrExposeSenderReceiverAliases(): void
    {
        $header = UblDocumentHeader::fromArray([
            'senderScheme'   => '0088',
            'senderValue'    => 'supplier',
            'receiverScheme' => '0088',
            'receiverValue'  => 'customer',
        ]);
        $values = $header->toArray();

        $this->assertNull($header->supplierEndpoint());
        $this->assertNull($header->customerEndpoint());
        $this->assertArrayHasKey('supplierEndpointScheme', $values);
        $this->assertArrayNotHasKey('senderScheme', $values);
        $this->assertArrayNotHasKey('receiverScheme', $values);
        $this->assertFalse(method_exists(UblDocumentHeader::class, 'sender'));
        $this->assertFalse(method_exists(UblDocumentHeader::class, 'receiver'));
    }

    public function testHeaderPreservesDateTextAndExposesOnlyValidLocalDates(): void
    {
        $header = UblDocumentHeader::fromArray([
            'issueDate'     => 'not-a-date',
            'deliveryDate'  => '2026-11-20',
            'payableAmount' => '001.2300',
        ]);

        $this->assertNull($header->issueDate());
        $this->assertSame('not-a-date', $header->toArray()['issueDate']);
        $this->assertSame('2026-11-20', $header->deliveryDate()->value());
        $this->assertSame('001.2300', $header->payableAmount());
        $this->assertSame($header->toArray(), UblDocumentHeader::fromArray($header->toArray())->toArray());
    }

    public function testBuyerReferenceSurvivesHydrationAndSerialization(): void
    {
        $header = UblDocumentHeader::fromArray([
            'buyerReference' => 'BUYER-REFERENCE-1',
        ]);

        $this->assertSame('BUYER-REFERENCE-1', $header->buyerReference());
        $this->assertSame(
            'BUYER-REFERENCE-1',
            UblDocumentHeader::fromArray($header->toArray())->buyerReference()
        );
    }
}
