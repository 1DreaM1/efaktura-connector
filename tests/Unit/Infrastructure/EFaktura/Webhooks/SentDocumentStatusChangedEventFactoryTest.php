<?php

namespace DreaM1\EfakturaConnector\Tests\Unit\Infrastructure\EFaktura\Webhooks;

use DreaM1\EfakturaConnector\Core\Webhooks\WebhookEventPayload;
use DreaM1\EfakturaConnector\Domain\EFaktura\ProviderDocumentStatus;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Webhooks\SentDocumentStatusChangedEventFactory;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/** Checks webhook status-event mapping, required metadata and outbound-only status validation. */
class SentDocumentStatusChangedEventFactoryTest extends TestCase
{
    public function testBuildsAnEventAndMapsTheOptionalErrorFlag(): void
    {
        $data = [
            'identifier'            => 'event-1',
            'documentId'            => 'document-1',
            'senderParticipantId'   => '0245:sender',
            'receiverParticipantId' => '0245:receiver',
            'status'                => ProviderDocumentStatus::REJECTED,
        ];

        $event = SentDocumentStatusChangedEventFactory::fromArray($data);
        $this->assertSame('event-1', $event->identifier());
        $this->assertSame('document-1', $event->documentId()->value());
        $this->assertSame('0245:sender', $event->sender()->identifier());
        $this->assertSame('0245:receiver', $event->receiver()->identifier());
        $this->assertSame(ProviderDocumentStatus::REJECTED, $event->status()->value());
        $this->assertFalse($event->hasError());

        $data['error'] = true;
        $this->assertTrue(SentDocumentStatusChangedEventFactory::fromArray($data)->hasError());
    }

    public function testPreservesUnknownProviderStatusForHostClassification(): void
    {
        $event = SentDocumentStatusChangedEventFactory::fromArray([
            'identifier'            => 'event-future',
            'documentId'            => 'document-future',
            'senderParticipantId'   => '0245:sender',
            'receiverParticipantId' => '0245:receiver',
            'status'                => 'FUTURE_DELIVERY_STATUS',
        ]);

        $this->assertSame('FUTURE_DELIVERY_STATUS', $event->status()->value());
        $this->assertFalse($event->status()->isKnown());
        $this->assertFalse($event->status()->isOutboundStatus());
    }

    public function testAnEmptyJsonErrorObjectIsStillReportedAsPresent(): void
    {
        $payload = WebhookEventPayload::fromJson((string) json_encode([
            'id'         => 'event-1',
            'event'      => 'sent_document.status_changed',
            'occurredAt' => '2026-11-01T10:00:00Z',
            'data'       => [
                'identifier'            => 'event-1',
                'documentId'            => 'document-1',
                'senderParticipantId'   => '0245:sender',
                'receiverParticipantId' => '0245:receiver',
                'status'                => ProviderDocumentStatus::REJECTED,
                'error'                 => (object) [],
            ],
        ]));

        $event = SentDocumentStatusChangedEventFactory::fromArray($payload->data());

        $this->assertTrue($event->hasError());
    }

    public function testRejectsEachMissingOrBlankRequiredField(): void
    {
        $valid = [
            'identifier'            => 'event-1',
            'documentId'            => 'document-1',
            'senderParticipantId'   => '0245:sender',
            'receiverParticipantId' => '0245:receiver',
            'status'                => ProviderDocumentStatus::REJECTED,
        ];

        foreach (array_keys($valid) as $field) {
            $missing = $valid;
            unset($missing[$field]);
            $blank         = $valid;
            $blank[$field] = " \t";

            foreach ([$missing, $blank] as $invalid) {
                try {
                    SentDocumentStatusChangedEventFactory::fromArray($invalid);
                    $this->fail('Missing or blank ' . $field . ' must be rejected.');
                } catch (InvalidArgumentException $exception) {
                    $this->assertNotSame('', $exception->getMessage());
                }
            }
        }
    }

    public function testRejectsNonOutboundAndNonStringStatuses(): void
    {
        $valid = [
            'identifier'            => 'event-1',
            'documentId'            => 'document-1',
            'senderParticipantId'   => '0245:sender',
            'receiverParticipantId' => '0245:receiver',
            'status'                => ProviderDocumentStatus::RECEIVED,
        ];

        foreach ([ProviderDocumentStatus::RECEIVED, 200] as $status) {
            $valid['status'] = $status;
            try {
                SentDocumentStatusChangedEventFactory::fromArray($valid);
                $this->fail('Invalid outbound statuses must be rejected.');
            } catch (InvalidArgumentException $exception) {
                $this->assertNotSame('', $exception->getMessage());
            }
        }
    }
}
