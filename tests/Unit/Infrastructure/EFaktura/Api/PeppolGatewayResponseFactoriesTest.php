<?php

namespace DreaM1\EfakturaConnector\Tests\Unit\Infrastructure\EFaktura\Api;

use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Response\PeppolGateway\ProviderMessageLevelStatusResponseFactory;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Response\PeppolGateway\SentDocumentStatusFactory;
use DreaM1\EfakturaConnector\Tests\Support\ApiFixtures;
use PHPUnit\Framework\TestCase;

/** Checks mapping of sent-document tracking data and message-level status responses. */
class PeppolGatewayResponseFactoriesTest extends TestCase
{
    public function testMapsSentDocumentResponseIntoTrackingStatus(): void
    {
        $status = SentDocumentStatusFactory::fromArray(ApiFixtures::sentDocument('DELIVERY_FAILED', [
            'identifier' => 'gateway-record/42',
            'provider'   => [
                'name'       => 'Synthetic AP',
                'documentId' => 'ap-document/42',
                'messageId'  => 'ap-message-42',
            ],
            'error' => [
                'message' => 'Synthetic delivery failure',
                'details' => [[
                    'field' => 'invoice.total',
                    'issue' => 'Value rejected',
                    'value' => '10.00',
                ]],
            ],
        ]));

        $this->assertSame('DELIVERY_FAILED', $status->status()->value());
        $this->assertSame('gateway-record/42', $status->identifier()->value());
        $this->assertSame('0245:2012345676', $status->sender()->identifier());
        $this->assertSame('0245:2012345699', $status->receiver()->identifier());
        $this->assertSame('INV-SYNTHETIC-1', $status->documentId()->value());
        $this->assertSame(ApiFixtures::DOCUMENT_TYPE_ID, $status->documentTypeId()->value());
        $this->assertSame(ApiFixtures::PROCESS_ID, $status->processId()->value());
        $this->assertSame('UBL_XML', $status->payloadFormat());
        $this->assertSame('2026-11-12T19:29:58.291Z', $status->createdAt()->value());
        $this->assertSame('2026-11-12T19:29:58.291Z', $status->updatedAt()->value());
        $this->assertSame('Synthetic AP', $status->provider()->name());
        $this->assertSame('ap-document/42', $status->provider()->documentId()->value());
        $this->assertSame('ap-message-42', $status->provider()->messageId()->value());
        $this->assertSame('Synthetic delivery failure', $status->error()->message());
        $this->assertSame('invoice.total', $status->error()->details()[0]->field());
        $this->assertSame('Value rejected', $status->error()->details()[0]->issue());
        $this->assertSame('10.00', $status->error()->details()[0]->value());
    }

    public function testMapsMessageLevelStatusAndReasons(): void
    {
        $response = ProviderMessageLevelStatusResponseFactory::fromArray(['items' => [
            ApiFixtures::messageLevelStatus('message-status-42', 'ap', [
                'reasons' => [[
                    'lineId'      => '3',
                    'code'        => 'BR-CO-10',
                    'description' => 'Synthetic validation reason',
                ]],
            ]),
        ]]);

        $status = $response->items()[0];

        $this->assertSame('message-status-42', $status->identifier()->value());
        $this->assertSame('AP', $status->responseCode()->value());
        $this->assertSame('Synthetic description', $status->responseDescription());
        $this->assertSame('0245:2012345699', $status->sender()->identifier());
        $this->assertSame('0245:2012345676', $status->receiver()->identifier());
        $this->assertSame('msg-message-status-42', $status->messageId()->value());
        $this->assertSame(ApiFixtures::DOCUMENT_TYPE_ID, $status->documentTypeId()->value());
        $this->assertSame('2026-11-12T19:29:58.291Z', $status->receivedAt()->value());
        $this->assertCount(1, $status->reasons());
        $this->assertSame('3', $status->reasons()[0]->lineId());
        $this->assertSame('BR-CO-10', $status->reasons()[0]->code());
        $this->assertSame('Synthetic validation reason', $status->reasons()[0]->description());
    }

    public function testSentDocumentMapperPreservesOmittedOptionalValues(): void
    {
        $status = SentDocumentStatusFactory::fromArray([]);

        $this->assertNull($status->status());
        $this->assertNull($status->identifier());
        $this->assertNull($status->provider());
        $this->assertNull($status->error());
        $this->assertNull($status->documentTypeId());
    }

    public function testSentDocumentMapperPreservesUnknownStatusAndPayloadFormat(): void
    {
        $status = SentDocumentStatusFactory::fromArray(ApiFixtures::sentDocument('DELIVERED_TO_ARCHIVE', [
            'payloadFormat' => 'FUTURE_XML_PROFILE',
        ]));

        $this->assertSame('DELIVERED_TO_ARCHIVE', $status->status()->value());
        $this->assertFalse($status->status()->isKnown());
        $this->assertFalse($status->status()->isOutboundStatus());
        $this->assertSame('FUTURE_XML_PROFILE', $status->payloadFormat());
    }
}
