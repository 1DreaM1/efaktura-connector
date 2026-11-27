<?php

namespace DreaM1\EfakturaConnector\Tests\Unit\Core\Webhooks;

use DateTimeImmutable;
use DreaM1\EfakturaConnector\Core\Exceptions\InvalidWebhookPayloadException;
use DreaM1\EfakturaConnector\Core\Webhooks\WebhookEventPayload;
use DreaM1\EfakturaConnector\Support\Timestamp;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/** Checks webhook envelope parsing, required event fields and preservation of event-specific data. */
class WebhookEventPayloadTest extends TestCase
{
    private const VALID_PAYLOAD = [
        'id'         => 'evt-synthetic-001',
        'event'      => 'sent_document.status_changed',
        'occurredAt' => '2026-11-01T10:00:00+01:00',
        'data'       => [
            'documentId'            => 'DOC-001',
            'senderParticipantId'   => '0088:synthetic-sender',
            'receiverParticipantId' => '0088:synthetic-receiver',
            'status'                => 'ACCEPTED',
        ],
    ];

    public function testFromArrayParsesAllFields(): void
    {
        $payload = WebhookEventPayload::fromArray(self::VALID_PAYLOAD);

        $this->assertSame('evt-synthetic-001', $payload->eventId()->value());
        $this->assertSame('sent_document.status_changed', $payload->eventType()->value());
        $this->assertTrue($payload->eventType()->isSupported());
        $this->assertSame(self::VALID_PAYLOAD['data'], $payload->data());
        $this->assertSame('2026-11-01T10:00:00+01:00', $payload->occurredAt()->value());
    }

    public function testFromJsonParsesValidJsonString(): void
    {
        $payload = WebhookEventPayload::fromJson(json_encode(self::VALID_PAYLOAD));

        $this->assertSame('evt-synthetic-001', $payload->eventId()->value());
        $this->assertSame('sent_document.status_changed', $payload->eventType()->value());
    }

    public function testFromJsonRejectsListForEventDataObject(): void
    {
        $payload         = self::VALID_PAYLOAD;
        $payload['data'] = array_values($payload['data']);

        $this->expectException(InvalidWebhookPayloadException::class);

        WebhookEventPayload::fromJson(json_encode($payload));
    }

    public function testFromJsonThrowsOnInvalidJson(): void
    {
        $this->expectException(InvalidArgumentException::class);
        WebhookEventPayload::fromJson('not-json');
    }

    public function testPreservesUnknownEventTypesForHostHandling(): void
    {
        $data          = self::VALID_PAYLOAD;
        $data['event'] = 'future.event';

        $payload = WebhookEventPayload::fromArray($data);

        $this->assertSame('future.event', $payload->eventType()->value());
        $this->assertFalse($payload->eventType()->isSupported());
    }

    /**
     * Rejects webhook payloads that omit a required field.
     *
     * @param string $field Required field omitted from the payload.
     * @dataProvider missingFieldProvider
     */
    public function testFromArrayThrowsWhenRequiredFieldMissing(string $field): void
    {
        $this->expectException(InvalidArgumentException::class);

        $data = self::VALID_PAYLOAD;
        unset($data[$field]);
        WebhookEventPayload::fromArray($data);
    }

    /**
     * Provides webhook payloads missing a required field.
     *
     * @return array<array-key, array<array-key, mixed>> Payload and missing-field cases.
     */
    public function missingFieldProvider(): array
    {
        return [
            'missing id'         => ['id'],
            'missing event'      => ['event'],
            'missing occurredAt' => ['occurredAt'],
            'missing data'       => ['data'],
        ];
    }

    public function testFromArrayThrowsWhenDataIsNotArray(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $data         = self::VALID_PAYLOAD;
        $data['data'] = 'not-an-array';
        WebhookEventPayload::fromArray($data);
    }

    public function testOccurredAtIsImmutable(): void
    {
        $payload = WebhookEventPayload::fromArray(self::VALID_PAYLOAD);

        $this->assertInstanceOf(Timestamp::class, $payload->occurredAt());
        $this->assertInstanceOf(DateTimeImmutable::class, $payload->occurredAt()->dateTimeImmutable());
    }

    public function testFromArrayAcceptsRfc3339FractionalTimestamp(): void
    {
        $data               = self::VALID_PAYLOAD;
        $data['occurredAt'] = '2026-11-01T10:00:00.123456789Z';

        $payload = WebhookEventPayload::fromArray($data);

        $this->assertSame('2026-11-01T10:00:00.123456789Z', $payload->occurredAt()->value());
        $this->assertSame('123456', $payload->occurredAt()->dateTimeImmutable()->format('u'));
    }

    public function testFromArrayAcceptsLowercaseRfc3339Separators(): void
    {
        $data               = self::VALID_PAYLOAD;
        $data['occurredAt'] = '2026-11-01t10:00:00z';

        $payload = WebhookEventPayload::fromArray($data);

        $this->assertSame(
            '2026-11-01T10:00:00+00:00',
            $payload->occurredAt()->dateTimeImmutable()->format(DATE_ATOM)
        );
    }

    public function testFromArrayAcceptsUnknownLocalOffset(): void
    {
        $data               = self::VALID_PAYLOAD;
        $data['occurredAt'] = '2026-11-01T10:00:00-00:00';

        $payload = WebhookEventPayload::fromArray($data);

        $this->assertSame('2026-11-01T10:00:00-00:00', $payload->occurredAt()->value());
        $this->assertSame(
            '2026-11-01T10:00:00+00:00',
            $payload->occurredAt()->dateTimeImmutable()->format(DATE_ATOM)
        );
    }

    public function testFromArrayAcceptsAnOffsetBeyondTheIso8601Profile(): void
    {
        $data               = self::VALID_PAYLOAD;
        $data['occurredAt'] = '2026-11-01T10:00:00+14:01';

        $payload = WebhookEventPayload::fromArray($data);

        $this->assertSame('+14:01', $payload->occurredAt()->dateTimeImmutable()->format('P'));
    }

    public function testFromArrayWrapsAnUnparseableTimestampAsInvalidArgument(): void
    {
        $data               = self::VALID_PAYLOAD;
        $data['occurredAt'] = 'not-a-timestamp';

        $this->expectException(InvalidArgumentException::class);
        WebhookEventPayload::fromArray($data);
    }

    /**
     * Rejects event timestamps that do not follow RFC 3339.
     *
     * @param string $occurredAt Candidate timestamp value.
     * @dataProvider invalidRfc3339TimestampProvider
     */
    public function testFromArrayRejectsOccurredAtOutsideRfc3339(string $occurredAt): void
    {
        $data               = self::VALID_PAYLOAD;
        $data['occurredAt'] = $occurredAt;

        $this->expectException(InvalidArgumentException::class);
        WebhookEventPayload::fromArray($data);
    }

    /**
     * Provides timestamps that are not valid RFC 3339 values.
     *
     * @return array<array-key, array<array-key, mixed>> Invalid timestamp cases.
     */
    public function invalidRfc3339TimestampProvider(): array
    {
        return [
            'human readable date'   => ['November 20, 2026 15:30 UTC'],
            'date only'             => ['2026-11-20'],
            'missing timezone'      => ['2026-11-20T15:30:00'],
            'invalid calendar date' => ['2026-02-30T15:30:00Z'],
            'invalid hour'          => ['2026-11-20T24:30:00Z'],
            'invalid offset'        => ['2026-11-20T15:30:00+24:00'],
            'trailing characters'   => ['2026-11-20T15:30:00Z extra'],
        ];
    }
}
