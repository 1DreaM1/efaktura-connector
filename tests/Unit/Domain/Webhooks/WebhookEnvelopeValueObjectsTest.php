<?php

namespace DreaM1\EfakturaConnector\Tests\Unit\Domain\Webhooks;

use DreaM1\EfakturaConnector\Domain\EFaktura\Webhooks\WebhookEventId;
use DreaM1\EfakturaConnector\Domain\EFaktura\Webhooks\WebhookEventType;
use DreaM1\EfakturaConnector\Domain\EFaktura\Webhooks\WebhookStatus;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/** Checks opaque webhook event IDs, known and unknown event names, and rejection of blank values. */
class WebhookEnvelopeValueObjectsTest extends TestCase
{
    public function testEventIdPreservesItsOpaqueValueAndComparesIds(): void
    {
        $id = WebhookEventId::fromString('event/42');

        $this->assertSame('event/42', $id->value());
        $this->assertTrue($id->equals(WebhookEventId::fromString('event/42')));
        $this->assertFalse($id->equals(WebhookEventId::fromString('event-42')));
    }

    public function testEventTypeRecognizesKnownNamesAndPreservesUnknownOnes(): void
    {
        $known   = WebhookEventType::fromString(WebhookEventType::SENT_DOCUMENT_STATUS_CHANGED);
        $unknown = WebhookEventType::fromString('future.event');

        $this->assertTrue($known->isSupported());
        $this->assertSame('future.event', $unknown->value());
        $this->assertFalse($unknown->isSupported());
    }

    public function testProviderWebhookStatusPreservesUnknownValuesForHostClassification(): void
    {
        $known   = WebhookStatus::fromProvider(WebhookStatus::ACTIVE);
        $unknown = WebhookStatus::fromProvider('PAUSED');

        $this->assertTrue($known->isKnown());
        $this->assertFalse($unknown->isKnown());
        $this->assertSame('PAUSED', $unknown->value());
    }

    public function testCallerWebhookStatusParserRemainsStrict(): void
    {
        $this->expectException(InvalidArgumentException::class);

        WebhookStatus::fromString('PAUSED');
    }

    public function testEventValuesRejectBlankStrings(): void
    {
        try {
            WebhookEventId::fromString(' ');
            $this->fail('Blank event IDs must be rejected.');
        } catch (InvalidArgumentException $exception) {
            $this->assertNotSame('', $exception->getMessage());
        }

        $this->expectException(InvalidArgumentException::class);
        WebhookEventType::fromString(" \t");
    }
}
