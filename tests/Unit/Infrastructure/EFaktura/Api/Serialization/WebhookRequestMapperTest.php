<?php

namespace DreaM1\EfakturaConnector\Tests\Unit\Infrastructure\EFaktura\Api\Serialization;

use DreaM1\EfakturaConnector\Core\Webhooks\CreateWebhookRequest;
use DreaM1\EfakturaConnector\Core\Webhooks\UpdateWebhookRequest;
use DreaM1\EfakturaConnector\Core\Webhooks\WebhookUpdate;
use DreaM1\EfakturaConnector\Core\Webhooks\WebhookUrl;
use DreaM1\EfakturaConnector\Domain\EFaktura\Webhooks\WebhookEventType;
use DreaM1\EfakturaConnector\Domain\EFaktura\Webhooks\WebhookIdentifier;
use DreaM1\EfakturaConnector\Domain\EFaktura\Webhooks\WebhookStatus;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Serialization\WebhookRequestMapper;
use PHPUnit\Framework\TestCase;

/** Webhook requests are mapped to the Peppol Gateway wire shape. */
final class WebhookRequestMapperTest extends TestCase
{
    public function testCreateBodyIncludesDescriptionWhenSupplied(): void
    {
        $body = WebhookRequestMapper::createBody(new CreateWebhookRequest(
            WebhookUrl::fromString('https://example.invalid/wh'),
            [WebhookEventType::fromString(WebhookEventType::INBOX_DOCUMENT_RECEIVED)],
            'Test'
        ));

        $this->assertSame(
            [
                'url'         => 'https://example.invalid/wh',
                'events'      => [WebhookEventType::INBOX_DOCUMENT_RECEIVED],
                'description' => 'Test',
            ],
            $body
        );
    }

    public function testCreateBodyOmitsAbsentDescription(): void
    {
        $body = WebhookRequestMapper::createBody(new CreateWebhookRequest(
            WebhookUrl::fromString('https://example.invalid/wh'),
            [WebhookEventType::fromString(WebhookEventType::SENT_DOCUMENT_STATUS_CHANGED)]
        ));

        $this->assertArrayNotHasKey('description', $body);
        $this->assertSame([WebhookEventType::SENT_DOCUMENT_STATUS_CHANGED], $body['events']);
    }

    public function testUpdateBodyMapsEachFieldIndependently(): void
    {
        $this->assertSame(
            ['status' => 'DISABLED'],
            WebhookRequestMapper::updateBody($this->update(
                WebhookUpdate::empty()->withStatus(WebhookStatus::fromString('DISABLED'))
            ))
        );
        $this->assertSame(
            ['description' => 'Only text'],
            WebhookRequestMapper::updateBody($this->update(WebhookUpdate::empty()->withDescription('Only text')))
        );
    }

    public function testUpdateBodyContainsOnlyTheSuppliedFields(): void
    {
        $body = WebhookRequestMapper::updateBody($this->update(
            WebhookUpdate::empty()
                ->withStatus(WebhookStatus::fromString('DISABLED'))
                ->withUrl(WebhookUrl::fromString('https://example.invalid/new'))
                ->withEvents([WebhookEventType::fromString(WebhookEventType::INBOX_DOCUMENT_RECEIVED)])
                ->withDescription('Renamed')
        ));

        $this->assertSame(
            [
                'url'         => 'https://example.invalid/new',
                'events'      => [WebhookEventType::INBOX_DOCUMENT_RECEIVED],
                'status'      => 'DISABLED',
                'description' => 'Renamed',
            ],
            $body
        );
    }

    public function testUpdateBodyKeepsExplicitNullsSoFieldsCanBeCleared(): void
    {
        $body = WebhookRequestMapper::updateBody($this->update(
            WebhookUpdate::empty()
                ->withUrl(null)
                ->withEvents(null)
                ->withStatus(null)
                ->withDescription(null)
        ));

        $this->assertSame(['url' => null, 'events' => null, 'status' => null, 'description' => null], $body);
    }

    private function update(WebhookUpdate $update): UpdateWebhookRequest
    {
        return new UpdateWebhookRequest(WebhookIdentifier::fromString('webhook-1'), $update);
    }
}
