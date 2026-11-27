<?php

namespace DreaM1\EfakturaConnector\Tests\Unit\Domain;

use DreaM1\EfakturaConnector\Domain\EFaktura\Participant;
use DreaM1\EfakturaConnector\Domain\EFaktura\PeppolDocumentTypeIdentifier;
use DreaM1\EfakturaConnector\Domain\EFaktura\PeppolProcessIdentifier;
use DreaM1\EfakturaConnector\Domain\EFaktura\ProviderDocumentId;
use DreaM1\EfakturaConnector\Domain\EFaktura\ReceivedDocumentMetadata;
use DreaM1\EfakturaConnector\Support\Timestamp;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/** Checks required routing metadata and participant/document identifiers for received documents. */
class ReceivedDocumentMetadataTest extends TestCase
{
    public function testCreatesAndSerializesValidMetadata(): void
    {
        $metadata = ReceivedDocumentMetadata::create(
            ProviderDocumentId::fromString('document-1'),
            Participant::parse('0245:sender'),
            Participant::parse('0245:receiver'),
            PeppolDocumentTypeIdentifier::fromString('invoice-type'),
            PeppolProcessIdentifier::fromString('process-1'),
            Timestamp::fromIso8601('2026-11-20T14:30:00Z')
        );

        $this->assertSame('document-1', $metadata->documentId()->value());
        $this->assertSame('0245:sender', $metadata->sender()->identifier());
        $this->assertSame('0245:receiver', $metadata->receiver()->identifier());
        $this->assertSame('invoice-type', $metadata->documentTypeId()->value());
        $this->assertSame('process-1', $metadata->processId()->value());
        $this->assertSame('2026-11-20T14:30:00Z', $metadata->creationDateTime()->value());
        $this->assertSame([
            'documentId'            => 'document-1',
            'senderParticipantId'   => '0245:sender',
            'receiverParticipantId' => '0245:receiver',
            'documentTypeId'        => 'invoice-type',
            'processId'             => 'process-1',
            'creationDateTime'      => '2026-11-20T14:30:00Z',
        ], $metadata->toArray());
    }

    public function testRejectsEmptyRequiredMetadata(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Provider document ID must not be empty.');

        ReceivedDocumentMetadata::create(
            ProviderDocumentId::fromString(' '),
            Participant::parse('0245:sender'),
            Participant::parse('0245:receiver'),
            PeppolDocumentTypeIdentifier::fromString('invoice-type'),
            PeppolProcessIdentifier::fromString('process-1'),
            Timestamp::fromIso8601('2026-11-20T14:30:00Z')
        );
    }

    public function testExposesSenderAndReceiverParticipants(): void
    {
        $metadata = ReceivedDocumentMetadata::create(
            ProviderDocumentId::fromString('document-1'),
            Participant::parse('0245:sender'),
            Participant::parse('0088:receiver'),
            PeppolDocumentTypeIdentifier::fromString('invoice-type'),
            PeppolProcessIdentifier::fromString('process-1'),
            Timestamp::fromIso8601('2026-11-20T14:30:00Z')
        );

        $this->assertEquals(Participant::parse('0245:sender'), $metadata->sender());
        $this->assertEquals(Participant::parse('0088:receiver'), $metadata->receiver());
    }

    public function testPreservesLongProviderMetadata(): void
    {
        $documentId     = str_repeat('d', 200);
        $senderId       = '0245:' . str_repeat('s', 100);
        $receiverId     = '0245:' . str_repeat('r', 100);
        $documentTypeId = str_repeat('t', 600);
        $processId      = str_repeat('p', 200);
        $metadata       = ReceivedDocumentMetadata::create(
            ProviderDocumentId::fromString($documentId),
            Participant::parse($senderId),
            Participant::parse($receiverId),
            PeppolDocumentTypeIdentifier::fromString($documentTypeId),
            PeppolProcessIdentifier::fromString($processId),
            Timestamp::fromIso8601('2026-11-20T14:30:00Z')
        );

        $this->assertSame($documentId, $metadata->documentId()->value());
        $this->assertSame($senderId, $metadata->sender()->identifier());
        $this->assertSame($receiverId, $metadata->receiver()->identifier());
        $this->assertSame($documentTypeId, $metadata->documentTypeId()->value());
        $this->assertSame($processId, $metadata->processId()->value());
        $this->assertSame('2026-11-20T14:30:00Z', $metadata->creationDateTime()->value());
    }
}
