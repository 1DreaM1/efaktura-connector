<?php

namespace DreaM1\EfakturaConnector\Tests\Unit\Infrastructure\EFaktura\Documents;

use DreaM1\EfakturaConnector\Core\Documents\DocumentRequestContext;
use DreaM1\EfakturaConnector\Core\Documents\SentDocumentReference;
use DreaM1\EfakturaConnector\Core\Documents\SentDocumentStatus;
use DreaM1\EfakturaConnector\Core\Exceptions\InvalidProviderResponseException;
use DreaM1\EfakturaConnector\Core\Exceptions\ProviderApiException;
use DreaM1\EfakturaConnector\Core\MessageStatuses\MessageLevelStatusContent;
use DreaM1\EfakturaConnector\Core\MessageStatuses\ProviderMessageLevelStatusResponse;
use DreaM1\EfakturaConnector\Domain\EFaktura\MessageLevelStatusIdentifier;
use DreaM1\EfakturaConnector\Domain\EFaktura\ProviderDocumentId;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Endpoints\PeppolGateway\GetMessageLevelStatusContentEndpoint;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Endpoints\PeppolGateway\GetSentDocumentEndpoint;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Endpoints\PeppolGateway\ListMessageLevelStatusesEndpoint;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Response\PeppolGateway\ProviderMessageLevelStatusResponseFactory;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Response\PeppolGateway\SentDocumentStatusFactory;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Documents\PeppolGatewayDocumentTrackingProvider;
use DreaM1\EfakturaConnector\Tests\Support\ApiFixtures;
use DreaM1\EfakturaConnector\Tests\Support\TestConfiguration;
use PHPUnit\Framework\TestCase;
use stdClass;

/** Tests Peppol Gateway sent-document tracking response mapping. */
class PeppolGatewayDocumentTrackingProviderTest extends TestCase
{
    public function testLoadsSentDocumentStatusWithTrackingContext(): void
    {
        $context = new DocumentRequestContext('42', 'INV-SYNTHETIC-42');
        $api     = $this->createMock(GetSentDocumentEndpoint::class);
        $api->expects($this->once())
            ->method('send')
            ->with($this->equalTo(new SentDocumentReference(
                TestConfiguration::participant(),
                ProviderDocumentId::fromString('provider/document-42'),
                $context
            )))
            ->willReturn(SentDocumentStatusFactory::fromArray(ApiFixtures::sentDocument('SENT_TO_ACCESS_POINT', [
                'identifier'            => 'gateway-record-42',
                'senderParticipantId'   => '0245:sender',
                'receiverParticipantId' => '0245:receiver',
                'documentId'            => 'INV-SYNTHETIC-42',
                'documentTypeId'        => ApiFixtures::DOCUMENT_TYPE_ID,
                'processId'             => ApiFixtures::PROCESS_ID,
                'payloadFormat'         => 'UBL_XML',
                'provider'              => [
                    'name'       => 'Synthetic AP',
                    'documentId' => 'ap-doc-42',
                    'messageId'  => 'ap-msg-42',
                ],
                'createdAt' => '2026-11-15T10:00:00Z',
                'updatedAt' => '2026-11-15T10:30:00Z',
            ])));

        $status = ($this->provider($api))->getSentDocumentStatus(new SentDocumentReference(
            TestConfiguration::participant(),
            ProviderDocumentId::fromString('provider/document-42'),
            $context
        ));

        $this->assertInstanceOf(SentDocumentStatus::class, $status);
        $this->assertSame('SENT_TO_ACCESS_POINT', $status->status()->value());
        $this->assertSame('0245:sender', $status->sender()->identifier());
        $this->assertSame('0245:receiver', $status->receiver()->identifier());
        $this->assertSame('gateway-record-42', $status->identifier()->value());
        $this->assertSame('INV-SYNTHETIC-42', $status->documentId()->value());
        $this->assertSame(ApiFixtures::DOCUMENT_TYPE_ID, $status->documentTypeId()->value());
        $this->assertSame(ApiFixtures::PROCESS_ID, $status->processId()->value());
        $this->assertSame('UBL_XML', $status->payloadFormat());
        $this->assertSame('Synthetic AP', $status->provider()->name());
        $this->assertSame('ap-doc-42', $status->provider()->documentId()->value());
        $this->assertSame('ap-msg-42', $status->provider()->messageId()->value());
        $this->assertSame('2026-11-15T10:00:00Z', $status->createdAt()->value());
        $this->assertSame('2026-11-15T10:30:00Z', $status->updatedAt()->value());
        $this->assertNull($status->error());
    }

    public function testMapsSentDocumentErrorMessage(): void
    {
        $api = $this->createMock(GetSentDocumentEndpoint::class);
        $api->method('send')->willReturn(SentDocumentStatusFactory::fromArray(ApiFixtures::sentDocument('DELIVERY_FAILED', [
            'error' => ['message' => 'Synthetic delivery failure'],
        ])));

        $status = ($this->provider($api))->getSentDocumentStatus(new SentDocumentReference(
            TestConfiguration::participant(),
            ProviderDocumentId::fromString('provider-42'),
            new DocumentRequestContext('42', null)
        ));

        $this->assertNotNull($status->error());
        $this->assertSame('Synthetic delivery failure', $status->error()->message());
    }

    public function testMapsSentDocumentErrorDetailsWithoutMessage(): void
    {
        $api = $this->createMock(GetSentDocumentEndpoint::class);
        $api->method('send')->willReturn(SentDocumentStatusFactory::fromArray(ApiFixtures::sentDocument('VALIDATION_FAILED', [
            'error' => ['details' => [[
                'field' => 'invoice.total',
                'issue' => 'Mismatch',
                'value' => '10',
            ]]],
        ])));

        $status = ($this->provider($api))->getSentDocumentStatus(new SentDocumentReference(
            TestConfiguration::participant(),
            ProviderDocumentId::fromString('provider-42'),
            new DocumentRequestContext('42', null)
        ));

        $this->assertSame('VALIDATION_FAILED', $status->status()->value());
        $this->assertNotNull($status->error());
        $this->assertSame('Mismatch', $status->error()->details()[0]->issue());
        $this->assertSame('invoice.total', $status->error()->details()[0]->field());
        $this->assertSame('10', $status->error()->details()[0]->value());
        $this->assertSame([
            'field' => 'invoice.total',
            'issue' => 'Mismatch',
            'value' => '10',
        ], $status->error()->details()[0]->toArray());
    }

    public function testPreservesEmptySentDocumentErrorObject(): void
    {
        $api = $this->createMock(GetSentDocumentEndpoint::class);
        $api->method('send')->willReturn(SentDocumentStatusFactory::fromArray(ApiFixtures::sentDocument('DELIVERY_FAILED', [
            'error' => new stdClass(),
        ])));

        $status = ($this->provider($api))->getSentDocumentStatus(new SentDocumentReference(
            TestConfiguration::participant(),
            ProviderDocumentId::fromString('provider-42'),
            new DocumentRequestContext('42', null)
        ));

        // Keep the empty error object: the provider status communicates the failure independently.
        $this->assertSame('DELIVERY_FAILED', $status->status()->value());
        $this->assertNotNull($status->error());
        $this->assertNull($status->error()->message());
        $this->assertSame([], $status->error()->details());
    }

    /**
     * Preserves missing or empty sender and receiver participant IDs independently.
     *
     * @dataProvider sentDocumentParticipantIdsWithMissingOrEmptyValues
     * @param array<string, mixed> $responseData                  Sent-document response fields under test.
     * @param string|null          $expectedSenderParticipantId   Expected sender ID, or null when omitted or blank.
     * @param string|null          $expectedReceiverParticipantId Expected receiver ID, or null when omitted or blank.
     */
    public function testMapsMissingOrEmptySentDocumentParticipantIdsIndependently(
        array $responseData,
        ?string $expectedSenderParticipantId,
        ?string $expectedReceiverParticipantId
    ): void {
        $api = $this->createMock(GetSentDocumentEndpoint::class);
        $api->method('send')->willReturn(SentDocumentStatusFactory::fromArray($responseData));

        $status = ($this->provider($api))->getSentDocumentStatus(new SentDocumentReference(
            TestConfiguration::participant(),
            ProviderDocumentId::fromString('provider-42'),
            new DocumentRequestContext('42', null)
        ));

        $this->assertSame(
            $expectedSenderParticipantId,
            $status->sender() === null ? null : $status->sender()->identifier()
        );
        $this->assertSame(
            $expectedReceiverParticipantId,
            $status->receiver() === null ? null : $status->receiver()->identifier()
        );
    }

    /**
     * Provides sent-document responses with missing or empty participant identifiers.
     *
     * @return array<string, array{array<string, mixed>, string|null, string|null}> Response and expected participant ID cases.
     */
    public function sentDocumentParticipantIdsWithMissingOrEmptyValues(): array
    {
        $senderId   = '0245:2012345676';
        $receiverId = '0245:2012345699';

        $senderOmitted = ApiFixtures::sentDocument('QUEUED');
        unset($senderOmitted['senderParticipantId']);

        $receiverOmitted = ApiFixtures::sentDocument('QUEUED');
        unset($receiverOmitted['receiverParticipantId']);

        return [
            'sender ID omitted'   => [$senderOmitted, null, $receiverId],
            'receiver ID omitted' => [$receiverOmitted, $senderId, null],
            'sender ID empty'     => [ApiFixtures::sentDocument('QUEUED', [
                'senderParticipantId' => '',
            ]), null, $receiverId],
            'receiver ID empty' => [ApiFixtures::sentDocument('QUEUED', [
                'receiverParticipantId' => '',
            ]), $senderId, null],
        ];
    }

    public function testMapsSchemaValidSentDocumentResponseWithoutOptionalStatus(): void
    {
        $api = $this->createMock(GetSentDocumentEndpoint::class);
        $api->method('send')->willReturn(SentDocumentStatusFactory::fromArray(['identifier' => 'provider-42']));

        $status = ($this->provider($api))->getSentDocumentStatus(new SentDocumentReference(
            TestConfiguration::participant(),
            ProviderDocumentId::fromString('provider-42'),
            new DocumentRequestContext('42', null)
        ));

        $this->assertSame('provider-42', $status->identifier()->value());
        $this->assertNull($status->status());
    }

    public function testPropagatesApiResponseExceptionFromSentDocumentStatus(): void
    {
        $api = $this->createMock(GetSentDocumentEndpoint::class);
        $api->method('send')->willThrowException(new InvalidProviderResponseException('Invalid status response.'));

        $this->expectException(ProviderApiException::class);

        ($this->provider($api))->getSentDocumentStatus(new SentDocumentReference(
            TestConfiguration::participant(),
            ProviderDocumentId::fromString('provider/document-42'),
            new DocumentRequestContext('42', 'INV-SYNTHETIC-42')
        ));
    }

    public function testKeepsSchemaValidStatusesAndCountsMalformedItems(): void
    {
        $context  = new DocumentRequestContext('42', 'INV-SYNTHETIC-42');
        $api      = $this->createMock(ListMessageLevelStatusesEndpoint::class);
        $response = ProviderMessageLevelStatusResponseFactory::fromArray(['items' => [
            ApiFixtures::messageLevelStatus('status-1', 'ap', [
                'messageId'             => 'message-1',
                'responseDescription'   => 'Message accepted',
                'senderParticipantId'   => '0245:sender',
                'receiverParticipantId' => '0245:receiver',
                'documentTypeId'        => ApiFixtures::DOCUMENT_TYPE_ID,
                'receivedAt'            => '2026-11-15T10:31:00Z',
                'reasons'               => [[
                    'lineId'      => '1',
                    'code'        => 'SV',
                    'description' => 'Synthetic reason',
                ]],
            ]),
            ApiFixtures::messageLevelStatus(' ', 'AP'),
            ApiFixtures::messageLevelStatus('status-3', 'A P'),
            ApiFixtures::messageLevelStatus('status-4', 'AP', ['senderParticipantId' => '']),
            ApiFixtures::messageLevelStatus('status-5', 'AP', ['receivedAt' => ['unexpected' => 'array']]),
        ]]);

        $this->assertCount(3, $response->items());
        $this->assertSame(2, $response->invalidItems());

        $api->expects($this->once())
            ->method('send')
            ->with(
                $this->equalTo(new SentDocumentReference(
                    TestConfiguration::participant(),
                    ProviderDocumentId::fromString('provider-42'),
                    $context
                ))
            )
            ->willReturn($response);

        $statuses = ($this->provider($api))->getMessageLevelStatuses(new SentDocumentReference(
            TestConfiguration::participant(),
            ProviderDocumentId::fromString('provider-42'),
            $context
        ));

        $this->assertInstanceOf(ProviderMessageLevelStatusResponse::class, $statuses);
        $this->assertTrue($statuses->isValid());
        $this->assertCount(3, $statuses->items());
        $this->assertSame(
            ['status-1', 'status-3', 'status-4'],
            array_map(static function ($item): ?string {
                return $item->identifier() !== null ? $item->identifier()->value() : null;
            }, $statuses->items())
        );
        $this->assertNull($statuses->items()[2]->sender());
        $this->assertSame('status-1', $statuses->items()[0]->identifier()->value());
        $this->assertSame('AP', $statuses->items()[0]->responseCode()->value());
        $this->assertSame('message-1', $statuses->items()[0]->messageId()->value());
        $this->assertSame('Message accepted', $statuses->items()[0]->responseDescription());
        $this->assertSame('0245:sender', $statuses->items()[0]->sender()->identifier());
        $this->assertSame('0245:receiver', $statuses->items()[0]->receiver()->identifier());
        $this->assertSame(ApiFixtures::DOCUMENT_TYPE_ID, $statuses->items()[0]->documentTypeId()->value());
        $this->assertSame('2026-11-15T10:31:00Z', $statuses->items()[0]->receivedAt()->value());
        $this->assertSame('1', $statuses->items()[0]->reasons()[0]->lineId());
        $this->assertSame('SV', $statuses->items()[0]->reasons()[0]->code());
        $this->assertSame('Synthetic reason', $statuses->items()[0]->reasons()[0]->description());
        $this->assertSame(2, $statuses->invalidItems());
    }

    public function testMissingMessageLevelStatusItemsAreValidAndRepresentAnEmptyList(): void
    {
        $api = $this->createMock(ListMessageLevelStatusesEndpoint::class);
        $api->method('send')->willReturn(ProviderMessageLevelStatusResponseFactory::fromArray([]));

        $statuses = ($this->provider($api))->getMessageLevelStatuses(new SentDocumentReference(
            TestConfiguration::participant(),
            ProviderDocumentId::fromString('provider-42'),
            new DocumentRequestContext('42', null)
        ));

        $this->assertTrue($statuses->isValid());
        $this->assertSame([], $statuses->items());
    }

    public function testDownloadsMessageLevelStatusXml(): void
    {
        $context  = new DocumentRequestContext('42', 'INV-SYNTHETIC-42');
        $xml      = '<MessageLevelStatus/>';
        $api      = $this->createMock(GetMessageLevelStatusContentEndpoint::class);
        $document = new SentDocumentReference(
            TestConfiguration::participant(),
            ProviderDocumentId::fromString('provider-42'),
            $context
        );
        $api->expects($this->once())
            ->method('send')
            ->with(
                $this->equalTo($document),
                $this->equalTo(MessageLevelStatusIdentifier::fromString('status/42'))
            )
            ->willReturn(new MessageLevelStatusContent($xml));

        $result = ($this->provider($api))->downloadMessageLevelStatus(
            $document,
            MessageLevelStatusIdentifier::fromString('status/42')
        );

        $this->assertInstanceOf(MessageLevelStatusContent::class, $result);
        $this->assertSame($xml, $result->xml());
    }

    /**
     * @param GetSentDocumentEndpoint|ListMessageLevelStatusesEndpoint|
     *        GetMessageLevelStatusContentEndpoint $endpoint Endpoint mock to inject; mocks are created for the
     *                                                       other tracking operations.
     */
    private function provider($endpoint): PeppolGatewayDocumentTrackingProvider
    {
        return new PeppolGatewayDocumentTrackingProvider(
            $endpoint instanceof GetSentDocumentEndpoint
                ? $endpoint : $this->createMock(GetSentDocumentEndpoint::class),
            $endpoint instanceof ListMessageLevelStatusesEndpoint
                ? $endpoint : $this->createMock(ListMessageLevelStatusesEndpoint::class),
            $endpoint instanceof GetMessageLevelStatusContentEndpoint
                ? $endpoint : $this->createMock(GetMessageLevelStatusContentEndpoint::class)
        );
    }
}
