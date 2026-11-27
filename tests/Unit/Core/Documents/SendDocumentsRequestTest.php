<?php

namespace DreaM1\EfakturaConnector\Tests\Unit\Core\Documents;

use DreaM1\EfakturaConnector\Core\Documents\IdempotencyKey;
use DreaM1\EfakturaConnector\Core\Documents\OutboundGatewayDocument;
use DreaM1\EfakturaConnector\Core\Documents\SendDocumentsRequest;
use DreaM1\EfakturaConnector\Core\Xml\XmlPayloadFormat;
use DreaM1\EfakturaConnector\Domain\EFaktura\Participant;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/** Tests Gateway batch request validation. */
final class SendDocumentsRequestTest extends TestCase
{
    public function testBatchLimitErrorUsesTheConfiguredMaximum(): void
    {
        $document  = new OutboundGatewayDocument(XmlPayloadFormat::SBDH_XML, '<StandardBusinessDocument/>');
        $documents = array_fill(0, SendDocumentsRequest::MAX_DOCUMENTS + 1, $document);

        try {
            new SendDocumentsRequest(
                Participant::parse('0245:synthetic-sender'),
                $documents,
                IdempotencyKey::fromString('00000000-0000-4000-8000-000000000001')
            );
            $this->fail('A Gateway batch above the configured document count must be rejected.');
        } catch (InvalidArgumentException $exception) {
            $this->assertSame('A Gateway batch may contain at most 100 documents.', $exception->getMessage());
        }
    }

    public function testRefusesAnEmptyBatch(): void
    {
        try {
            new SendDocumentsRequest(
                Participant::parse('0245:synthetic-sender'),
                [],
                IdempotencyKey::fromString('00000000-0000-4000-8000-000000000001')
            );
            $this->fail('A Gateway batch without documents must be rejected before any provider call.');
        } catch (InvalidArgumentException $exception) {
            $this->assertSame('A Gateway batch must contain at least one document.', $exception->getMessage());
        }
    }

    public function testAcceptsASingleDocumentBatch(): void
    {
        $document = new OutboundGatewayDocument(XmlPayloadFormat::SBDH_XML, '<StandardBusinessDocument/>');
        $request  = new SendDocumentsRequest(
            Participant::parse('0245:synthetic-sender'),
            [7 => $document],
            IdempotencyKey::fromString('00000000-0000-4000-8000-000000000001')
        );

        $this->assertSame([$document], $request->documents());
    }

    public function testRejectsItemsThatAreNotOutboundGatewayDocuments(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Gateway batch items must be OutboundGatewayDocument instances.');

        new SendDocumentsRequest(
            Participant::parse('0245:synthetic-sender'),
            ['not a document'],
            IdempotencyKey::fromString('00000000-0000-4000-8000-000000000001')
        );
    }
}
