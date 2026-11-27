<?php

namespace DreaM1\EfakturaConnector\Tests\Unit\Core\Documents;

use DreaM1\EfakturaConnector\Core\Documents\OutboundDocumentPayload;
use DreaM1\EfakturaConnector\Domain\EFaktura\Participant;
use DreaM1\EfakturaConnector\Domain\EFaktura\PeppolDocumentTypeIdentifier;
use DreaM1\EfakturaConnector\Domain\EFaktura\PeppolProcessIdentifier;
use DreaM1\EfakturaConnector\Domain\EFaktura\SubmittedDocumentId;
use DreaM1\EfakturaConnector\Support\Timestamp;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/** Checks outbound participant metadata and the allowed identifier schemes for new submissions. */
class OutboundDocumentPayloadTest extends TestCase
{
    public function testExposesParticipantsAndTheirStringIdentifiers(): void
    {
        $sender   = Participant::parse('0245:sender');
        $receiver = Participant::parse('0088:receiver');
        $payload  = new OutboundDocumentPayload(
            SubmittedDocumentId::fromString('document-1'),
            PeppolDocumentTypeIdentifier::fromString('invoice-type'),
            PeppolProcessIdentifier::fromString('process-1'),
            $sender,
            $receiver,
            Timestamp::fromIso8601('2026-11-20T14:30:00Z'),
            '<Invoice/>'
        );

        $this->assertSame('document-1', $payload->documentId()->value());
        $this->assertSame($sender, $payload->sender());
        $this->assertSame('0245', $payload->sender()->scheme());
        $this->assertSame('sender', $payload->sender()->value());
        $this->assertSame('0245:sender', $payload->sender()->identifier());

        $this->assertSame($receiver, $payload->receiver());
        $this->assertSame('0088', $payload->receiver()->scheme());
        $this->assertSame('receiver', $payload->receiver()->value());
        $this->assertSame('0088:receiver', $payload->receiver()->identifier());
    }

    /**
     * Rejects participant identifiers using schemes unsupported for new documents.
     *
     * @param string $receiverIdentifier Receiver identifier supplied to the payload.
     * @dataProvider unsupportedParticipantSchemes
     */
    public function testRejectsSchemesNotAllowedForNewDocuments(string $receiverIdentifier): void
    {
        $this->expectException(InvalidArgumentException::class);

        new OutboundDocumentPayload(
            SubmittedDocumentId::fromString('document-1'),
            PeppolDocumentTypeIdentifier::fromString('invoice-type'),
            PeppolProcessIdentifier::fromString('process-1'),
            Participant::parse('0245:sender'),
            Participant::parse($receiverIdentifier),
            Timestamp::fromIso8601('2026-11-20T14:30:00Z'),
            '<Invoice/>'
        );
    }

    /**
     * Provides participant schemes that outbound payloads must reject.
     *
     * @return array<array-key, array<array-key, mixed>> Invalid scheme cases.
     */
    public function unsupportedParticipantSchemes(): array
    {
        return [
            'deprecated scheme'                      => ['0193:sample'],
            'deprecated scheme without removal date' => ['9909:sample'],
            'active but not registrable scheme'      => ['0205:synthetic'],
            'unknown scheme'                         => ['9999:synthetic'],
        ];
    }

    public function testRejectsRemovedSenderSchemeForANewDocument(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new OutboundDocumentPayload(
            SubmittedDocumentId::fromString('document-1'),
            PeppolDocumentTypeIdentifier::fromString('invoice-type'),
            PeppolProcessIdentifier::fromString('process-1'),
            Participant::parse('0037:sample'),
            Participant::parse('0088:receiver'),
            Timestamp::fromIso8601('2026-11-20T14:30:00Z'),
            '<Invoice/>'
        );
    }
}
