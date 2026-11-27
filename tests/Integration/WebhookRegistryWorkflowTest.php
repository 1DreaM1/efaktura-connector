<?php

namespace DreaM1\EfakturaConnector\Tests\Integration;

use DreaM1\EfakturaConnector\Core\Exceptions\InvalidProviderResponseException;
use DreaM1\EfakturaConnector\Core\Exceptions\ProviderErrorResponseException;
use DreaM1\EfakturaConnector\Core\Pagination\PageSize;
use DreaM1\EfakturaConnector\Core\Webhooks\CreateWebhookRequest;
use DreaM1\EfakturaConnector\Core\Webhooks\DeleteWebhookRequest;
use DreaM1\EfakturaConnector\Core\Webhooks\ListWebhooksRequest;
use DreaM1\EfakturaConnector\Core\Webhooks\UpdateWebhookRequest;
use DreaM1\EfakturaConnector\Core\Webhooks\WebhookRegistrationResponse;
use DreaM1\EfakturaConnector\Core\Webhooks\WebhookRegistryPage;
use DreaM1\EfakturaConnector\Core\Webhooks\WebhookUpdate;
use DreaM1\EfakturaConnector\Core\Webhooks\WebhookUrl;
use DreaM1\EfakturaConnector\Domain\EFaktura\Webhooks\WebhookEventType;
use DreaM1\EfakturaConnector\Domain\EFaktura\Webhooks\WebhookIdentifier;
use DreaM1\EfakturaConnector\Domain\EFaktura\Webhooks\WebhookStatus;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Endpoints\PeppolGateway\DeleteWebhookEndpoint;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Endpoints\PeppolGateway\GetWebhookEndpoint;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Endpoints\PeppolGateway\ListWebhooksEndpoint;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Endpoints\PeppolGateway\RegisterWebhookEndpoint;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Endpoints\PeppolGateway\UpdateWebhookEndpoint;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Transport\Execution\HttpRequestExecutorInterface;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Transport\HttpExchangeRequest;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Webhooks\PeppolGatewayWebhookRegistry;
use DreaM1\EfakturaConnector\Tests\Support\ApiFixtures;

/** Exercises webhook registration, pagination, lookup, update and deletion with synthetic Gateway responses. */
class WebhookRegistryWorkflowTest extends WorkflowTestCase
{
    // ── 6c Register webhook ──────────────────────────────────────────────────

    public function testRegisterWebhookAcquiresTokenAndReturnsRegistrationResponse(): void
    {
        $executor = $this->queuedExecutor([
            $this->tokenExchange(),
            $this->jsonExchange(201, ApiFixtures::webhookCreate('synthetic-webhook-id', 'synthetic-signing-secret')),
        ]);

        $registration = $this->buildRegistry($executor)->register(
            $this->participant(),
            new CreateWebhookRequest(
                WebhookUrl::fromString('https://protap.example.invalid/api/efaktura/webhook'),
                [WebhookEventType::fromString(WebhookEventType::SENT_DOCUMENT_STATUS_CHANGED)],
                'Integration test webhook'
            )
        );

        $this->assertInstanceOf(WebhookRegistrationResponse::class, $registration);
        $this->assertSame('synthetic-webhook-id', $registration->id()->value());
        $this->assertSame('synthetic-signing-secret', $registration->signingSecret()->reveal());
    }

    public function testWebhookRegistrationProviderConflictIsPreserved(): void
    {
        $executor = $this->queuedExecutor([
            $this->tokenExchange(),
            $this->jsonExchange(409, ApiFixtures::error('Synthetic webhook already exists.', [], 'CONFLICT')),
        ]);

        try {
            $this->buildRegistry($executor)->register(
                $this->participant(),
                new CreateWebhookRequest(
                    WebhookUrl::fromString('https://protap.example.invalid/api/efaktura/webhook'),
                    [WebhookEventType::fromString(WebhookEventType::SENT_DOCUMENT_STATUS_CHANGED)]
                )
            );
            $this->fail('A registration conflict must be surfaced to the caller.');
        } catch (ProviderErrorResponseException $exception) {
            $this->assertSame(409, $exception->httpStatusCode()->value());
            $this->assertSame('Synthetic webhook already exists.', $exception->errorEnvelope()->message());
        }
    }

    // ── 6c List webhooks page ────────────────────────────────────────────────

    public function testListWebhooksPageReturnsWebhookCollection(): void
    {
        $executor = $this->queuedExecutor([
            $this->tokenExchange(),
            $this->jsonExchange(200, ApiFixtures::webhookList([ApiFixtures::webhook('wh-1')])),
        ]);

        $page = $this->buildRegistry($executor)->listPage(
            $this->participant(),
            new ListWebhooksRequest(null, PageSize::fromInt(50))
        );

        $this->assertInstanceOf(WebhookRegistryPage::class, $page);
        $this->assertCount(1, $page->webhooks()->items());
        $this->assertNull($page->nextPageToken());
    }

    public function testListWebhooksPreservesUnknownProviderStatusAndEventType(): void
    {
        $executor = $this->queuedExecutor([
            $this->tokenExchange(),
            $this->jsonExchange(200, ApiFixtures::webhookList([
                ApiFixtures::webhook('wh-future', [
                    'status' => 'PAUSED',
                    'events' => ['future.event'],
                ]),
            ])),
        ]);

        $page = $this->buildRegistry($executor)->listPage(
            $this->participant(),
            new ListWebhooksRequest(null, PageSize::fromInt(50))
        );
        $webhook = $page->webhooks()->items()[0];

        $this->assertSame('PAUSED', $webhook->status()->value());
        $this->assertFalse($webhook->status()->isKnown());
        $this->assertSame('future.event', $webhook->events()[0]->value());
        $this->assertFalse($webhook->events()[0]->isSupported());
    }

    public function testListWebhooksEmptyPageReturnsEmptyCollection(): void
    {
        $executor = $this->queuedExecutor([
            $this->tokenExchange(),
            $this->jsonExchange(200, ['webhooks' => [], 'nextPageToken' => null]),
        ]);

        $page = $this->buildRegistry($executor)->listPage(
            $this->participant(),
            new ListWebhooksRequest(null, PageSize::fromInt(50))
        );

        $this->assertCount(0, $page->webhooks()->items());
    }

    public function testGetWebhookUsesParticipantScopedDetailEndpoint(): void
    {
        $participant = $this->participant();
        $queue       = [
            $this->tokenExchange(),
            $this->jsonExchange(200, ApiFixtures::webhook('synthetic-webhook-id')),
        ];
        $requests = [];
        $executor = $this->createMock(HttpRequestExecutorInterface::class);
        $executor->expects($this->exactly(2))
            ->method('execute')
            ->willReturnCallback(function (HttpExchangeRequest $request) use (&$queue, &$requests) {
                $requests[] = $request;

                return array_shift($queue);
            });

        $webhook = $this->buildRegistry($executor)->get(
            $participant,
            WebhookIdentifier::fromString('synthetic-webhook-id')
        );

        $this->assertSame('synthetic-webhook-id', $webhook->id()->value());
        $this->assertSame('GET', $requests[1]->httpMethod()->value());
        $this->assertSame('/v1/webhooks/synthetic-webhook-id', $requests[1]->path());
        $participantHeader = null;

        foreach ($requests[1]->headers()->all() as $header) {
            if ($header->name() === 'x-peppol-participant-id') {
                $participantHeader = $header->value();

                break;
            }
        }

        $this->assertSame($participant->identifier(), $participantHeader);
        $this->assertSame(self::BASE_URL, $requests[1]->baseUrlOrNull()->value());
    }

    public function testUpdateWebhookChangesEndpointEventsAndStatus(): void
    {
        $updatedUrl  = 'https://protap.example.invalid/api/efaktura/webhook-v2';
        $eventTypes  = [WebhookEventType::fromString(WebhookEventType::INBOX_DOCUMENT_RECEIVED)];
        $eventValues = [WebhookEventType::INBOX_DOCUMENT_RECEIVED];
        $executor    = $this->queuedExecutor([
            $this->tokenExchange(),
            $this->jsonExchange(200, ApiFixtures::webhook('synthetic-webhook-id', [
                'url'         => $updatedUrl,
                'events'      => $eventValues,
                'status'      => 'DISABLED',
                'description' => 'Synthetic receiver endpoint',
            ])),
        ]);

        $webhook = $this->buildRegistry($executor)->update(
            $this->participant(),
            new UpdateWebhookRequest(
                WebhookIdentifier::fromString('synthetic-webhook-id'),
                WebhookUpdate::empty()
                    ->withUrl(WebhookUrl::fromString($updatedUrl))
                    ->withEvents($eventTypes)
                    ->withStatus(WebhookStatus::fromString('DISABLED'))
                    ->withDescription('Synthetic receiver endpoint')
            )
        );

        $this->assertSame('synthetic-webhook-id', $webhook->id()->value());
        $this->assertSame($updatedUrl, $webhook->url()->value());
        $this->assertSame($eventValues, array_map(
            static function (WebhookEventType $event): string {
                return $event->value();
            },
            $webhook->events()
        ));
        $this->assertSame('DISABLED', $webhook->status()->value());
        $this->assertSame('Synthetic receiver endpoint', $webhook->description());
    }

    public function testWebhookUpdateRejectsMalformedSuccessfulResponse(): void
    {
        $executor = $this->queuedExecutor([
            $this->tokenExchange(),
            $this->jsonExchange(200, [
                'url'       => 'https://protap.example.invalid/api/efaktura/webhook',
                'events'    => ['sent_document.status_changed'],
                'status'    => 'ACTIVE',
                'createdAt' => '2026-11-20T14:00:00Z',
                'updatedAt' => '2026-11-20T14:30:00Z',
            ]),
        ]);

        try {
            $this->buildRegistry($executor)->update(
                $this->participant(),
                new UpdateWebhookRequest(
                    WebhookIdentifier::fromString('synthetic-webhook-id'),
                    WebhookUpdate::empty()->withStatus(WebhookStatus::fromString('DISABLED'))
                )
            );
            $this->fail('An update response without the webhook ID must be rejected.');
        } catch (InvalidProviderResponseException $exception) {
            $this->assertSame('Webhook', $exception->schema());
            $this->assertSame('id', $exception->field());
            $this->assertSame(200, $exception->httpStatusCode()->value());
        }
    }

    // ── 6c Delete webhook ────────────────────────────────────────────────────

    public function testDeleteExistingWebhookReturnsTrue(): void
    {
        $executor = $this->queuedExecutor([
            $this->tokenExchange(),
            $this->jsonExchange(204, []),
        ]);

        $deleted = $this->buildRegistry($executor)->delete(
            $this->participant(),
            new DeleteWebhookRequest(WebhookIdentifier::fromString('synthetic-webhook-id'))
        );

        $this->assertTrue($deleted);
    }

    public function testDeleteNonExistentWebhookReturnsFalse(): void
    {
        $executor = $this->queuedExecutor([
            $this->tokenExchange(),
            $this->jsonExchange(404, ApiFixtures::error('Webhook was not found')),
        ]);

        $deleted = $this->buildRegistry($executor)->delete(
            $this->participant(),
            new DeleteWebhookRequest(WebhookIdentifier::fromString('non-existent-webhook-id'))
        );

        $this->assertFalse($deleted);
    }

    public function testDeleteProviderServerErrorIsNotReportedAsNotFound(): void
    {
        $executor = $this->queuedExecutor([
            $this->tokenExchange(),
            $this->jsonExchange(503, ApiFixtures::error('Synthetic webhook service outage.')),
        ]);

        try {
            $this->buildRegistry($executor)->delete(
                $this->participant(),
                new DeleteWebhookRequest(WebhookIdentifier::fromString('synthetic-webhook-id'))
            );
            $this->fail('A provider outage must be propagated instead of treated as a missing webhook.');
        } catch (ProviderErrorResponseException $exception) {
            $this->assertSame(503, $exception->httpStatusCode()->value());
            $this->assertSame('Synthetic webhook service outage.', $exception->errorEnvelope()->message());
        }
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    /**
     * Creates a webhook registry adapter using the supplied HTTP executor.
     *
     * @param  HttpRequestExecutorInterface $executor Executor used for provider requests.
     * @return PeppolGatewayWebhookRegistry Configured webhook registry.
     */
    private function buildRegistry($executor): PeppolGatewayWebhookRegistry
    {
        $connector = $this->buildConnector($executor);

        return new PeppolGatewayWebhookRegistry(
            new RegisterWebhookEndpoint($connector),
            new UpdateWebhookEndpoint($connector),
            new DeleteWebhookEndpoint($connector),
            new GetWebhookEndpoint($connector),
            new ListWebhooksEndpoint($connector)
        );
    }
}
