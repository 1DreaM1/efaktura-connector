<?php

namespace DreaM1\EfakturaConnector\Tests\Unit\Core\Webhooks;

use DreaM1\EfakturaConnector\Core\Webhooks\Webhook;
use DreaM1\EfakturaConnector\Core\Webhooks\WebhookEvents;
use DreaM1\EfakturaConnector\Core\Webhooks\WebhookList;
use DreaM1\EfakturaConnector\Core\Webhooks\WebhookRegistrationResponse;
use DreaM1\EfakturaConnector\Core\Webhooks\WebhookSigningSecret;
use DreaM1\EfakturaConnector\Core\Webhooks\WebhookUrl;
use DreaM1\EfakturaConnector\Domain\EFaktura\Webhooks\WebhookEventType;
use DreaM1\EfakturaConnector\Domain\EFaktura\Webhooks\WebhookIdentifier;
use DreaM1\EfakturaConnector\Domain\EFaktura\Webhooks\WebhookStatus;
use DreaM1\EfakturaConnector\Support\Timestamp;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/** Covers validation and value semantics for webhook registration and delivery inputs. */
class WebhookValueObjectsTest extends TestCase
{
    public function testAcceptsAbsoluteHttpsUrlsIncludingAnUppercaseScheme(): void
    {
        $url             = WebhookUrl::fromString('https://example.test/hooks/efaktura');
        $uppercaseScheme = WebhookUrl::fromString('HTTPS://example.test/hooks/efaktura');
        $this->assertSame('https://example.test/hooks/efaktura', $url->value());
        $this->assertSame('HTTPS://example.test/hooks/efaktura', $uppercaseScheme->value());
        $this->addToAssertionCount(1);
    }

    /**
     * Rejects webhook URLs that are not absolute HTTPS URLs.
     *
     * @param string $url Candidate webhook URL.
     * @dataProvider invalidUrls
     */
    public function testRejectsUrlsThatAreNotAbsoluteHttpsUrls(string $url): void
    {
        $this->expectException(InvalidArgumentException::class);
        WebhookUrl::fromString($url);
    }

    /**
     * Provides webhook URLs that are not accepted as absolute HTTPS addresses.
     *
     * @return array<array-key, array<array-key, mixed>> Invalid URL cases.
     */
    public function invalidUrls(): array
    {
        return [
            'plain HTTP'      => ['http://example.test/hook'],
            'relative path'   => ['/hooks/efaktura'],
            'missing host'    => ['https:///hooks/efaktura'],
            'URL credentials' => ['https://user:password@example.test/hooks/efaktura'],
            'invalid URL'     => ['not a URL'],
        ];
    }

    public function testWebhookNormalizesItsEventIndexesAndSerializesItsFields(): void
    {
        $webhook = new Webhook(
            WebhookIdentifier::fromString('hook-1'),
            WebhookUrl::fromString('https://example.test/hook'),
            [
                4 => WebhookEventType::fromString(WebhookEventType::SENT_DOCUMENT_STATUS_CHANGED),
                9 => WebhookEventType::fromString(WebhookEventType::INBOX_DOCUMENT_RECEIVED),
            ],
            WebhookStatus::fromString(WebhookStatus::ACTIVE),
            'Invoice updates',
            Timestamp::fromIso8601('2026-11-20T14:00:00Z'),
            Timestamp::fromIso8601('2026-11-20T14:30:00Z')
        );

        $this->assertSame('hook-1', $webhook->id()->value());
        $this->assertSame('https://example.test/hook', $webhook->url()->value());
        $this->assertSame(
            ['sent_document.status_changed', 'inbox_document.received'],
            array_map(static function (WebhookEventType $event): string {
                return $event->value();
            }, $webhook->events())
        );
        $this->assertSame(WebhookStatus::ACTIVE, $webhook->status()->value());
        $this->assertSame('Invoice updates', $webhook->description());
        $this->assertSame('2026-11-20T14:00:00Z', $webhook->createdAt()->value());
        $this->assertSame('2026-11-20T14:30:00Z', $webhook->updatedAt()->value());
        $this->assertSame([
            'id'          => 'hook-1',
            'url'         => 'https://example.test/hook',
            'events'      => ['sent_document.status_changed', 'inbox_document.received'],
            'status'      => WebhookStatus::ACTIVE,
            'description' => 'Invoice updates',
            'createdAt'   => '2026-11-20T14:00:00Z',
            'updatedAt'   => '2026-11-20T14:30:00Z',
        ], $webhook->toArray());
    }

    public function testRejectsInvalidWebhookStateAndMalformedEvents(): void
    {
        foreach (
            [
            static function (): void {
                new Webhook(
                    WebhookIdentifier::fromString(''),
                    WebhookUrl::fromString('https://example.test'),
                    [],
                    WebhookStatus::fromString(WebhookStatus::ACTIVE),
                    null,
                    Timestamp::fromIso8601('2026-11-20T14:00:00Z'),
                    Timestamp::fromIso8601('2026-11-20T14:30:00Z')
                );
            },
            static function (): void {
                new Webhook(
                    WebhookIdentifier::fromString('hook-1'),
                    WebhookUrl::fromString(' '),
                    [],
                    WebhookStatus::fromString(WebhookStatus::ACTIVE),
                    null,
                    Timestamp::fromIso8601('2026-11-20T14:00:00Z'),
                    Timestamp::fromIso8601('2026-11-20T14:30:00Z')
                );
            },
            static function (): void {
                new Webhook(
                    WebhookIdentifier::fromString('hook-1'),
                    WebhookUrl::fromString('https://example.test'),
                    [],
                    WebhookStatus::fromString('PAUSED'),
                    null,
                    Timestamp::fromIso8601('2026-11-20T14:00:00Z'),
                    Timestamp::fromIso8601('2026-11-20T14:30:00Z')
                );
            },
            static function (): void {
                new Webhook(
                    WebhookIdentifier::fromString('hook-1'),
                    WebhookUrl::fromString('https://example.test'),
                    [''],
                    WebhookStatus::fromString(WebhookStatus::ACTIVE),
                    null,
                    Timestamp::fromIso8601('2026-11-20T14:00:00Z'),
                    Timestamp::fromIso8601('2026-11-20T14:30:00Z')
                );
            },
            static function (): void {
                WebhookEvents::normalize([WebhookEventType::fromString('sent_document.status_changed'), 4]);
            },
            static function (): void {
                WebhookEvents::normalize([WebhookEventType::fromString('sent_document.deleted')]);
            },
            ] as $invalidConstruction
        ) {
            try {
                $invalidConstruction();
                $this->fail('Malformed webhook values must be rejected.');
            } catch (InvalidArgumentException $exception) {
                $this->assertNotSame('', $exception->getMessage());
            }
        }
    }

    public function testWebhookListReindexesValuesAndRejectsNonWebhookItems(): void
    {
        $webhook = new Webhook(
            WebhookIdentifier::fromString('hook-1'),
            WebhookUrl::fromString('https://example.test'),
            [],
            WebhookStatus::fromString(WebhookStatus::ACTIVE),
            null,
            Timestamp::fromIso8601('2026-11-20T14:00:00Z'),
            Timestamp::fromIso8601('2026-11-20T14:30:00Z')
        );
        $list = new WebhookList([7 => $webhook]);

        $this->assertSame([$webhook], $list->items());

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Webhook lists must contain webhook values.');
        new WebhookList(['not a webhook']);
    }

    public function testRegistrationResponseRequiresCredentialsAndNormalizesEvents(): void
    {
        $response = new WebhookRegistrationResponse(
            WebhookIdentifier::fromString('hook-1'),
            WebhookSigningSecret::fromString('signing-secret'),
            [5 => WebhookEventType::fromString(WebhookEventType::SENT_DOCUMENT_STATUS_CHANGED)],
            WebhookStatus::fromString(WebhookStatus::ACTIVE),
            Timestamp::fromIso8601('2026-11-20T14:00:00Z'),
            Timestamp::fromIso8601('2026-11-20T14:30:00Z'),
            WebhookUrl::fromString('https://example.test/hook'),
            null
        );
        $this->assertSame('sent_document.status_changed', $response->events()[0]->value());
        $this->assertSame('hook-1', $response->id()->value());
        $this->assertInstanceOf(WebhookSigningSecret::class, $response->signingSecret());
        $this->assertSame('signing-secret', $response->signingSecret()->reveal());
        $this->assertSame(WebhookStatus::ACTIVE, $response->status()->value());
        $this->assertSame('https://example.test/hook', $response->url()->value());
        $this->assertSame('2026-11-20T14:00:00Z', $response->createdAt()->value());
        $this->assertSame('2026-11-20T14:30:00Z', $response->updatedAt()->value());

        foreach (
            [
            static function (): void {
                new WebhookRegistrationResponse(
                    WebhookIdentifier::fromString(''),
                    WebhookSigningSecret::fromString('secret'),
                    [],
                    WebhookStatus::fromString(WebhookStatus::ACTIVE),
                    Timestamp::fromIso8601('2026-11-20T14:00:00Z'),
                    Timestamp::fromIso8601('2026-11-20T14:30:00Z'),
                    WebhookUrl::fromString('https://example.test/hook')
                );
            },
            static function (): void {
                new WebhookRegistrationResponse(
                    WebhookIdentifier::fromString('hook-1'),
                    WebhookSigningSecret::fromString(' '),
                    [],
                    WebhookStatus::fromString(WebhookStatus::ACTIVE),
                    Timestamp::fromIso8601('2026-11-20T14:00:00Z'),
                    Timestamp::fromIso8601('2026-11-20T14:30:00Z'),
                    WebhookUrl::fromString('https://example.test/hook')
                );
            },
            static function (): void {
                new WebhookRegistrationResponse(
                    WebhookIdentifier::fromString('hook-1'),
                    WebhookSigningSecret::fromString('secret'),
                    [''],
                    WebhookStatus::fromString(WebhookStatus::ACTIVE),
                    Timestamp::fromIso8601('2026-11-20T14:00:00Z'),
                    Timestamp::fromIso8601('2026-11-20T14:30:00Z'),
                    WebhookUrl::fromString('https://example.test/hook')
                );
            },
            ] as $invalidConstruction
        ) {
            try {
                $invalidConstruction();
                $this->fail('Invalid webhook registration data must be rejected.');
            } catch (InvalidArgumentException $exception) {
                $this->assertNotSame('', $exception->getMessage());
            }
        }
    }

    public function testWebhookModelsPreserveUnknownProviderEventsAndNormalizeIndexes(): void
    {
        $unknownEvent = WebhookEventType::fromString('sent_document.future_event');

        $webhook = new Webhook(
            WebhookIdentifier::fromString('hook-1'),
            WebhookUrl::fromString('https://example.test/hook'),
            [7 => $unknownEvent],
            WebhookStatus::fromString(WebhookStatus::ACTIVE),
            null,
            Timestamp::fromIso8601('2026-11-20T14:00:00Z'),
            Timestamp::fromIso8601('2026-11-20T14:30:00Z')
        );
        $response = new WebhookRegistrationResponse(
            WebhookIdentifier::fromString('hook-2'),
            WebhookSigningSecret::fromString('signing-secret'),
            [9 => $unknownEvent],
            WebhookStatus::fromString(WebhookStatus::ACTIVE),
            Timestamp::fromIso8601('2026-11-20T14:00:00Z'),
            Timestamp::fromIso8601('2026-11-20T14:30:00Z'),
            WebhookUrl::fromString('https://example.test/hook')
        );

        $this->assertSame([$unknownEvent], $webhook->events());
        $this->assertSame([$unknownEvent], $response->events());
    }
}
