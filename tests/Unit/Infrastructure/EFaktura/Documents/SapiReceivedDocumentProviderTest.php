<?php

namespace DreaM1\EfakturaConnector\Tests\Unit\Infrastructure\EFaktura\Documents;

use DreaM1\EfakturaConnector\Core\Documents\DocumentRequestContext;
use DreaM1\EfakturaConnector\Core\Documents\ListReceivedDocumentsRequest;
use DreaM1\EfakturaConnector\Core\Documents\ReceivedDocumentPage;
use DreaM1\EfakturaConnector\Core\Documents\ReceivedDocumentPayload;
use DreaM1\EfakturaConnector\Core\Documents\ReceivedDocumentReference;
use DreaM1\EfakturaConnector\Core\Pagination\PageSize;
use DreaM1\EfakturaConnector\Core\Pagination\PageToken;
use DreaM1\EfakturaConnector\Domain\EFaktura\Participant;
use DreaM1\EfakturaConnector\Domain\EFaktura\PeppolDocumentTypeIdentifier;
use DreaM1\EfakturaConnector\Domain\EFaktura\PeppolProcessIdentifier;
use DreaM1\EfakturaConnector\Domain\EFaktura\ProviderDocumentId;
use DreaM1\EfakturaConnector\Domain\EFaktura\ReceivedDocumentMetadata;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Endpoints\Sapi\AcknowledgeReceivedDocumentEndpoint;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Endpoints\Sapi\GetReceivedDocumentEndpoint;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Endpoints\Sapi\ListReceivedDocumentsEndpoint;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Response\Sapi\ReceivedDocumentAcknowledgementFactory;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Documents\SapiReceivedDocumentProvider;
use DreaM1\EfakturaConnector\Support\Timestamp;
use DreaM1\EfakturaConnector\Tests\Support\ApiFixtures;
use DreaM1\EfakturaConnector\Tests\Support\TestConfiguration;
use PHPUnit\Framework\TestCase;

/** Tests received-document listing, retrieval and acknowledgement through SAPI. */
class SapiReceivedDocumentProviderTest extends TestCase
{
    public function testListsInboxPageWithEncodedCursor(): void
    {
        $api = $this->createMock(ListReceivedDocumentsEndpoint::class);
        $api->expects($this->once())
            ->method('send')
            ->with($this->equalTo(new ListReceivedDocumentsRequest(
                TestConfiguration::participant(),
                PageToken::fromString('next page'),
                PageSize::fromInt(50)
            )))
            ->willReturn(new ReceivedDocumentPage(
                [ReceivedDocumentMetadata::create(
                    ProviderDocumentId::fromString('doc-1'),
                    Participant::parse('0245:sender'),
                    Participant::parse('0245:receiver'),
                    PeppolDocumentTypeIdentifier::fromString(ApiFixtures::DOCUMENT_TYPE_ID),
                    PeppolProcessIdentifier::fromString(ApiFixtures::PROCESS_ID),
                    Timestamp::fromIso8601(ApiFixtures::TIMESTAMP)
                )],
                PageToken::fromString('cursor-2')
            ));

        $result = ($this->gateway($api))->getInboxPage(new ListReceivedDocumentsRequest(
            TestConfiguration::participant(),
            PageToken::fromString('next page'),
            PageSize::fromInt(50)
        ));

        $this->assertInstanceOf(ReceivedDocumentPage::class, $result);
        $this->assertCount(1, $result->documents());
        $this->assertSame('doc-1', $result->documents()[0]->documentId()->value());
        $this->assertSame(ApiFixtures::DOCUMENT_TYPE_ID, $result->documents()[0]->documentTypeId()->value());
        $this->assertSame('0245:sender', $result->documents()[0]->sender()->identifier());
        $this->assertSame('0245:receiver', $result->documents()[0]->receiver()->identifier());
        $this->assertSame(ApiFixtures::PROCESS_ID, $result->documents()[0]->processId()->value());
        $this->assertSame(ApiFixtures::TIMESTAMP, $result->documents()[0]->creationDateTime()->value());
        $this->assertSame('cursor-2', $result->nextPageToken()->value());
    }

    public function testPreservesZeroAsAValidInboxCursor(): void
    {
        $api = $this->createMock(ListReceivedDocumentsEndpoint::class);
        $api->expects($this->once())
            ->method('send')
            ->with($this->equalTo(new ListReceivedDocumentsRequest(
                TestConfiguration::participant(),
                PageToken::fromString('0'),
                PageSize::fromInt(20)
            )))
            ->willReturn(new ReceivedDocumentPage([], null));

        ($this->gateway($api))->getInboxPage(new ListReceivedDocumentsRequest(
            TestConfiguration::participant(),
            PageToken::fromString('0'),
            PageSize::fromInt(20)
        ));
    }

    public function testLoadsReceivedDocumentPayloadWithDiagnosticContext(): void
    {
        $context       = new DocumentRequestContext('21', 'INV-SYNTHETIC-21');
        $payload       = '<Invoice/>';
        $payloadFormat = 'application/xml';
        $api           = $this->createMock(GetReceivedDocumentEndpoint::class);
        $api->expects($this->once())
            ->method('send')
            ->with($this->equalTo(new ReceivedDocumentReference(
                TestConfiguration::participant(),
                ProviderDocumentId::fromString('provider/document-21'),
                $context
            )))
            ->willReturn(new ReceivedDocumentPayload(
                $payload,
                $payloadFormat,
                ReceivedDocumentMetadata::create(
                    ProviderDocumentId::fromString('provider/document-21'),
                    Participant::parse('0245:sender'),
                    Participant::parse('0245:receiver'),
                    PeppolDocumentTypeIdentifier::fromString(ApiFixtures::DOCUMENT_TYPE_ID),
                    PeppolProcessIdentifier::fromString(ApiFixtures::PROCESS_ID),
                    Timestamp::fromIso8601(ApiFixtures::TIMESTAMP)
                )
            ));

        $result = ($this->gateway($api))->getDocumentPayload(new ReceivedDocumentReference(
            TestConfiguration::participant(),
            ProviderDocumentId::fromString('provider/document-21'),
            $context
        ));

        $this->assertInstanceOf(ReceivedDocumentPayload::class, $result);
        $this->assertSame($payload, $result->xml());
        $this->assertSame($payloadFormat, $result->payloadFormat());
        $this->assertSame('provider/document-21', $result->metadata()->documentId()->value());
        $this->assertSame('0245:sender', $result->metadata()->sender()->identifier());
        $this->assertSame('0245:receiver', $result->metadata()->receiver()->identifier());
        $this->assertSame(ApiFixtures::DOCUMENT_TYPE_ID, $result->metadata()->documentTypeId()->value());
        $this->assertSame(ApiFixtures::PROCESS_ID, $result->metadata()->processId()->value());
        $this->assertSame(ApiFixtures::TIMESTAMP, $result->metadata()->creationDateTime()->value());
    }

    public function testAcknowledgesReceivedDocumentUsingProviderEndpoint(): void
    {
        $context = new DocumentRequestContext('21', 'INV-SYNTHETIC-21');
        $api     = $this->createMock(AcknowledgeReceivedDocumentEndpoint::class);
        $api->expects($this->once())
            ->method('send')
            ->with($this->equalTo(new ReceivedDocumentReference(
                TestConfiguration::participant(),
                ProviderDocumentId::fromString('provider-21'),
                $context
            )))
            ->willReturn(ReceivedDocumentAcknowledgementFactory::fromArray(ApiFixtures::acknowledge('provider-21')));

        $result = ($this->gateway($api))->acknowledge(new ReceivedDocumentReference(
            TestConfiguration::participant(),
            ProviderDocumentId::fromString('provider-21'),
            $context
        ));

        $this->assertSame('provider-21', $result->documentId()->value());
        $this->assertSame('ACKNOWLEDGED', $result->status()->value());
        $this->assertSame(ApiFixtures::TIMESTAMP, $result->acknowledgedDateTime()->value());
    }

    /**
     * Builds the gateway with the given endpoint mock; the other endpoints are inert mocks.
     *
     * @param ListReceivedDocumentsEndpoint|GetReceivedDocumentEndpoint|AcknowledgeReceivedDocumentEndpoint $endpoint
     *                                                                                                                Endpoint mock to inject; mocks are created for the other received-document operations.
     */
    private function gateway($endpoint): SapiReceivedDocumentProvider
    {
        return new SapiReceivedDocumentProvider(
            $endpoint instanceof ListReceivedDocumentsEndpoint
                ? $endpoint : $this->createMock(ListReceivedDocumentsEndpoint::class),
            $endpoint instanceof GetReceivedDocumentEndpoint
                ? $endpoint : $this->createMock(GetReceivedDocumentEndpoint::class),
            $endpoint instanceof AcknowledgeReceivedDocumentEndpoint
                ? $endpoint : $this->createMock(AcknowledgeReceivedDocumentEndpoint::class)
        );
    }
}
