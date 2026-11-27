<?php

namespace DreaM1\EfakturaConnector\Tests\Unit\Core\Documents;

use DreaM1\EfakturaConnector\Core\Documents\DocumentRequestContext;
use DreaM1\EfakturaConnector\Core\Documents\DocumentSubmissionResult;
use DreaM1\EfakturaConnector\Core\Documents\ReceivedDocumentPage;
use DreaM1\EfakturaConnector\Core\Documents\ReceivedDocumentPayload;
use DreaM1\EfakturaConnector\Core\Documents\SentDocumentError;
use DreaM1\EfakturaConnector\Core\Documents\SentDocumentParties;
use DreaM1\EfakturaConnector\Core\MessageStatuses\ProviderMessageLevelStatus;
use DreaM1\EfakturaConnector\Core\MessageStatuses\ProviderMessageLevelStatusReason;
use DreaM1\EfakturaConnector\Core\MessageStatuses\ProviderMessageLevelStatusResponse;
use DreaM1\EfakturaConnector\Core\MessageStatuses\ProviderMessageLevelStatusResult;
use DreaM1\EfakturaConnector\Core\Pagination\PageToken;
use DreaM1\EfakturaConnector\Core\ProviderErrors\ProviderErrorDetail;
use DreaM1\EfakturaConnector\Domain\EFaktura\MessageLevelStatusCode;
use DreaM1\EfakturaConnector\Domain\EFaktura\MessageLevelStatusIdentifier;
use DreaM1\EfakturaConnector\Domain\EFaktura\Participant;
use DreaM1\EfakturaConnector\Domain\EFaktura\PeppolDocumentTypeIdentifier;
use DreaM1\EfakturaConnector\Domain\EFaktura\PeppolProcessIdentifier;
use DreaM1\EfakturaConnector\Domain\EFaktura\ProviderDocumentId;
use DreaM1\EfakturaConnector\Domain\EFaktura\ProviderDocumentStatus;
use DreaM1\EfakturaConnector\Domain\EFaktura\ReceivedDocumentMetadata;
use DreaM1\EfakturaConnector\Support\Timestamp;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use stdClass;

/** Covers document request contexts, submission results, received payloads and message-status collections. */
class DocumentValueObjectsTest extends TestCase
{
    public function testDocumentRequestContextExposesAndSerializesItsValues(): void
    {
        $context = new DocumentRequestContext(' document-1 ', 'INV-1');

        $this->assertSame(' document-1 ', $context->documentId());
        $this->assertSame('INV-1', $context->documentNumber());
        $this->assertSame(['document_id' => ' document-1 ', 'document_number' => 'INV-1'], $context->toArray());

        $withoutNumber = new DocumentRequestContext('document-2', null);
        $this->assertNull($withoutNumber->documentNumber());
        $this->assertSame(['document_id' => 'document-2', 'document_number' => null], $withoutNumber->toArray());
    }

    public function testDocumentRequestContextRejectsAnEmptyId(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Document ID must not be empty.');
        new DocumentRequestContext(" \t", null);
    }

    public function testSubmissionResultExposesAllProviderFields(): void
    {
        $result = new DocumentSubmissionResult(
            ProviderDocumentId::fromString('provider-1'),
            ProviderDocumentStatus::fromString('ACCEPTED'),
            Timestamp::fromIso8601('2026-11-20T14:30:00Z'),
            Timestamp::fromIso8601('2026-11-20T14:29:59Z')
        );

        $this->assertSame('provider-1', $result->providerDocumentId()->value());
        $this->assertSame('ACCEPTED', $result->status()->value());
        $this->assertSame('2026-11-20T14:30:00Z', $result->timestamp()->value());
        $this->assertSame('2026-11-20T14:29:59Z', $result->receivedAt()->value());
        $this->assertNull((new DocumentSubmissionResult(
            ProviderDocumentId::fromString('provider-2'),
            ProviderDocumentStatus::fromString('REJECTED'),
            Timestamp::fromIso8601('2026-11-20T14:30:00Z')
        ))->receivedAt());
    }

    public function testReceivedDocumentPageValidatesAndReindexesDocuments(): void
    {
        $document = ReceivedDocumentMetadata::create(
            ProviderDocumentId::fromString('document-1'),
            Participant::parse('0245:sender'),
            Participant::parse('0245:receiver'),
            PeppolDocumentTypeIdentifier::fromString('invoice-type'),
            PeppolProcessIdentifier::fromString('process-1'),
            Timestamp::fromIso8601('2026-11-20T14:30:00Z')
        );
        $page = new ReceivedDocumentPage([4 => $document], PageToken::fromString('next-page'));

        $this->assertSame([$document], $page->documents());
        $this->assertSame('next-page', $page->nextPageToken()->value());
        $this->assertNull((new ReceivedDocumentPage([], null))->nextPageToken());
    }

    public function testReceivedDocumentPageRejectsValuesOutsideTheMetadataType(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Received document pages must contain only metadata values.');
        new ReceivedDocumentPage([new stdClass()], null);
    }

    public function testSentDocumentErrorValidatesAndReindexesDetails(): void
    {
        $detail = ProviderErrorDetail::fromArray(['field' => 'amount']);
        $error  = new SentDocumentError('Invalid amount', [7 => $detail]);

        $this->assertSame('Invalid amount', $error->message());
        $this->assertSame([$detail], $error->details());

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Sent document error details must be provider error detail values.');
        new SentDocumentError(null, ['not a provider error detail']);
    }

    public function testReceivedDocumentPayloadExposesXmlAndProviderFormat(): void
    {
        $metadata = ReceivedDocumentMetadata::create(
            ProviderDocumentId::fromString('document-1'),
            Participant::parse('0245:sender'),
            Participant::parse('0245:receiver'),
            PeppolDocumentTypeIdentifier::fromString('invoice-type'),
            PeppolProcessIdentifier::fromString('process-1'),
            Timestamp::fromIso8601('2026-11-20T14:30:00Z')
        );
        $payload = new ReceivedDocumentPayload('<Invoice/>', 'application/xml', $metadata);

        $this->assertSame('<Invoice/>', $payload->xml());
        $this->assertSame('application/xml', $payload->payloadFormat());
        $this->assertSame($metadata, $payload->metadata());
    }

    public function testProviderMessageStatusAndReasonsExposeTheirValues(): void
    {
        $reason      = ProviderMessageLevelStatusReason::create('line-1', 'BR-1', 'Invalid amount');
        $emptyReason = ProviderMessageLevelStatusReason::create(null, null, null);
        $status      = ProviderMessageLevelStatus::create(
            MessageLevelStatusIdentifier::fromString('status-1'),
            null,
            new ProviderMessageLevelStatusResult(
                MessageLevelStatusCode::fromString('REJECTED'),
                'Document rejected',
                [7 => $reason, 9 => $emptyReason]
            ),
            new SentDocumentParties(Participant::parse('0245:sender'), Participant::parse('0245:receiver')),
            null,
            Timestamp::fromIso8601('2026-11-20T14:30:00Z')
        );

        $this->assertSame('status-1', $status->identifier()->value());
        $this->assertSame('REJECTED', $status->responseCode()->value());
        $this->assertSame('0245:sender', $status->sender()->identifier());
        $this->assertSame('0245:receiver', $status->receiver()->identifier());
        $this->assertSame('Document rejected', $status->responseDescription());
        $this->assertSame('2026-11-20T14:30:00Z', $status->receivedAt()->value());
        $this->assertSame([$reason, $emptyReason], $status->reasons());
        $this->assertSame('line-1', $reason->lineId());
        $this->assertSame('BR-1', $reason->code());
        $this->assertSame('Invalid amount', $reason->description());
        $this->assertNull($emptyReason->lineId());
        $this->assertNull($emptyReason->code());
        $this->assertNull($emptyReason->description());
    }

    public function testProviderMessageStatusRejectsInvalidReasonValues(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Message-level status reasons must contain reason values.');

        new ProviderMessageLevelStatusResult(null, null, ['invalid reason']);
    }

    public function testProviderMessageStatusResponseValidatesAndReindexesItems(): void
    {
        $status = ProviderMessageLevelStatus::create(
            null,
            null,
            new ProviderMessageLevelStatusResult(),
            new SentDocumentParties()
        );
        $result = ProviderMessageLevelStatusResponse::create(true, [4 => $status], 0);

        $this->assertSame([$status], $result->items());

        try {
            ProviderMessageLevelStatusResponse::create(true, ['invalid status'], 0);
            $this->fail('Expected a non-status item to be rejected.');
        } catch (InvalidArgumentException $exception) {
            $this->assertSame('Message-status responses must contain status values.', $exception->getMessage());
        }
    }

    public function testProviderMessageStatusResponseRejectsNegativeInvalidItemCount(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid message-status item count must not be negative.');

        ProviderMessageLevelStatusResponse::create(true, [], -1);
    }
}
