<?php

namespace DreaM1\EfakturaConnector\Tests\Unit\Domain;

use DreaM1\EfakturaConnector\Domain\EFaktura\Participant;
use DreaM1\EfakturaConnector\Domain\EFaktura\ProviderDocumentId;
use DreaM1\EfakturaConnector\Domain\EFaktura\ProviderDocumentStatus;
use DreaM1\EfakturaConnector\Domain\EFaktura\Webhooks\SentDocumentStatusChangedEvent;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/** Checks required event metadata, outbound statuses and the provider-error presence flag. */
class SentDocumentStatusChangedEventTest extends TestCase
{
    public function testCreatesOutboundStatusEvent(): void
    {
        $event = SentDocumentStatusChangedEvent::create(
            'synthetic-provider-document',
            ProviderDocumentId::fromString('synthetic-document'),
            Participant::parse('0245:sender'),
            Participant::parse('0245:receiver'),
            ProviderDocumentStatus::fromString('REJECTED'),
            true
        );

        $this->assertSame('REJECTED', $event->status()->value());
        $this->assertTrue($event->hasError());
    }
    public function testExposesTheCompleteEventMetadata(): void
    {
        $event = SentDocumentStatusChangedEvent::create(
            'event-1',
            ProviderDocumentId::fromString('document-1'),
            Participant::parse('0245:sender'),
            Participant::parse('0245:receiver'),
            ProviderDocumentStatus::fromString(ProviderDocumentStatus::DELIVERY_FAILED),
            false
        );

        $this->assertSame('event-1', $event->identifier());
        $this->assertSame('document-1', $event->documentId()->value());
        $this->assertSame('0245:sender', $event->sender()->identifier());
        $this->assertSame('0245:receiver', $event->receiver()->identifier());
        $this->assertSame(ProviderDocumentStatus::DELIVERY_FAILED, $event->status()->value());
        $this->assertFalse($event->hasError());
    }

    public function testRejectsStatusThatCannotBelongToAnOutboundDocument(): void
    {
        $this->expectException(InvalidArgumentException::class);

        SentDocumentStatusChangedEvent::create(
            'synthetic-provider-document',
            ProviderDocumentId::fromString('synthetic-document'),
            Participant::parse('0245:sender'),
            Participant::parse('0245:receiver'),
            ProviderDocumentStatus::fromString(ProviderDocumentStatus::RECEIVED),
            false
        );
    }

    public function testPreservesUnknownProviderStatusForHostClassification(): void
    {
        $event = SentDocumentStatusChangedEvent::create(
            'synthetic-provider-document',
            ProviderDocumentId::fromString('synthetic-document'),
            Participant::parse('0245:sender'),
            Participant::parse('0245:receiver'),
            ProviderDocumentStatus::fromProvider('FUTURE_STATUS'),
            false
        );

        $this->assertSame('FUTURE_STATUS', $event->status()->value());
        $this->assertFalse($event->status()->isKnown());
        $this->assertFalse($event->status()->isOutboundStatus());
    }

    public function testRejectsEmptyRequiredEventValues(): void
    {
        $this->expectException(InvalidArgumentException::class);

        SentDocumentStatusChangedEvent::create(
            '',
            ProviderDocumentId::fromString('synthetic-document'),
            Participant::parse('0245:sender'),
            Participant::parse('0245:receiver'),
            ProviderDocumentStatus::fromString('REJECTED'),
            false
        );
    }
}
