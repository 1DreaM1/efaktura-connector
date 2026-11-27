<?php

namespace DreaM1\EfakturaConnector\Tests\Unit\Infrastructure\EFaktura\Webhooks;

use DreaM1\EfakturaConnector\Core\Exceptions\InvalidProviderResponseException;
use DreaM1\EfakturaConnector\Core\Exceptions\ProviderApiException;
use DreaM1\EfakturaConnector\Core\Exceptions\ProviderErrorResponseException;
use DreaM1\EfakturaConnector\Core\Pagination\PageSize;
use DreaM1\EfakturaConnector\Core\Pagination\PageToken;
use DreaM1\EfakturaConnector\Core\Webhooks\CreateWebhookRequest;
use DreaM1\EfakturaConnector\Core\Webhooks\DeleteWebhookRequest;
use DreaM1\EfakturaConnector\Core\Webhooks\ListWebhooksRequest;
use DreaM1\EfakturaConnector\Core\Webhooks\UpdateWebhookRequest;
use DreaM1\EfakturaConnector\Core\Webhooks\Webhook;
use DreaM1\EfakturaConnector\Core\Webhooks\WebhookRegistrationResponse;
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
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Response\PeppolGateway\WebhookFactory;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Response\PeppolGateway\WebhookRegistrationResponseFactory;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Response\PeppolGateway\WebhookRegistryPageFactory;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Webhooks\PeppolGatewayWebhookRegistry;
use DreaM1\EfakturaConnector\Tests\Support\ApiFixtures;
use DreaM1\EfakturaConnector\Tests\Support\TestConfiguration;
use PHPUnit\Framework\TestCase;

/** Tests Peppol Gateway webhook registration, lookup and removal. */
class PeppolGatewayWebhookRegistryTest extends TestCase
{
    public function testRegisterUsesWebhookEndpointAndRequestContract(): void
    {
        $request = new CreateWebhookRequest(
            WebhookUrl::fromString('https://protap.example.invalid/api/efaktura/webhook'),
            [WebhookEventType::fromString(WebhookEventType::SENT_DOCUMENT_STATUS_CHANGED)],
            'Protap'
        );
        $api = $this->createMock(RegisterWebhookEndpoint::class);
        $api->expects($this->once())
            ->method('send')
            ->with($this->equalTo(TestConfiguration::participant()), $this->equalTo($request))
            ->willReturn(WebhookRegistrationResponseFactory::fromArray(
                ApiFixtures::webhookCreate('synthetic-webhook', 'synthetic-secret')
            ));

        $registry = $this->gateway($api);

        $registration = $registry->register(TestConfiguration::participant(), $request);

        $this->assertInstanceOf(WebhookRegistrationResponse::class, $registration);
        $this->assertSame('synthetic-webhook', $registration->id()->value());
        $this->assertSame('synthetic-secret', $registration->signingSecret()->reveal());
        $this->assertSame('https://erp.example.test/peppol/webhooks', $registration->url()->value());
        $this->assertSame('Synthetic endpoint', $registration->description());
        $this->assertSame('sent_document.status_changed', $registration->events()[0]->value());
        $this->assertSame('inbox_document.received', $registration->events()[1]->value());
        $this->assertSame('ACTIVE', $registration->status()->value());
        $this->assertSame(ApiFixtures::TIMESTAMP, $registration->createdAt()->value());
        $this->assertSame(ApiFixtures::TIMESTAMP, $registration->updatedAt()->value());
    }

    public function testListPageEncodesCursorInProviderRequest(): void
    {
        $request = new ListWebhooksRequest(PageToken::fromString('next page'), PageSize::fromInt(100));
        $api     = $this->createMock(ListWebhooksEndpoint::class);
        $api->expects($this->once())
            ->method('send')
            ->with($this->equalTo(TestConfiguration::participant()), $this->equalTo($request))
            ->willReturn(WebhookRegistryPageFactory::fromArray(
                ApiFixtures::webhookList([ApiFixtures::webhook('wh-1')])
            ));

        $result = ($this->gateway($api))->listPage(
            TestConfiguration::participant(),
            $request
        );

        $this->assertCount(1, $result->webhooks()->items());
        $webhook = $result->webhooks()->items()[0];
        $this->assertSame('wh-1', $webhook->id()->value());
        $this->assertSame('https://erp.example.test/peppol/webhooks', $webhook->url()->value());
        $this->assertSame('sent_document.status_changed', $webhook->events()[0]->value());
        $this->assertSame('inbox_document.received', $webhook->events()[1]->value());
        $this->assertSame('ACTIVE', $webhook->status()->value());
        $this->assertSame('Synthetic endpoint', $webhook->description());
        $this->assertSame(ApiFixtures::TIMESTAMP, $webhook->createdAt()->value());
        $this->assertSame(ApiFixtures::TIMESTAMP, $webhook->updatedAt()->value());
        $this->assertNull($result->nextPageToken());
    }

    public function testListPageAllowsLongCursorAndPreservesLongProviderCursor(): void
    {
        $cursor  = str_repeat('c', 5000);
        $request = new ListWebhooksRequest(PageToken::fromString($cursor), PageSize::fromInt(100));
        $api     = $this->createMock(ListWebhooksEndpoint::class);
        $api->expects($this->once())
            ->method('send')
            ->with($this->equalTo(TestConfiguration::participant()), $this->equalTo($request))
            ->willReturn(WebhookRegistryPageFactory::fromArray(ApiFixtures::webhookList([], $cursor)));

        $result = ($this->gateway($api))->listPage(
            TestConfiguration::participant(),
            $request
        );

        $this->assertSame($cursor, $result->nextPageToken()->value());
    }

    public function testListPageForwardsLimitWithoutUndocumentedBounds(): void
    {
        $request = new ListWebhooksRequest(null, PageSize::fromInt(101));
        $api     = $this->createMock(ListWebhooksEndpoint::class);
        $api->expects($this->once())
            ->method('send')
            ->with($this->equalTo(TestConfiguration::participant()), $this->equalTo($request))
            ->willReturn(WebhookRegistryPageFactory::fromArray(ApiFixtures::webhookList([])));

        $result = ($this->gateway($api))->listPage(TestConfiguration::participant(), $request);

        $this->assertSame([], $result->webhooks()->items());
    }

    public function testListPageRejectsEmptyRequiredWebhookFieldsAsAnInvalidProviderResponse(): void
    {
        $api = $this->createMock(ListWebhooksEndpoint::class);
        $api->method('send')->willThrowException(new InvalidProviderResponseException(
            'Webhook list response is missing the webhooks array.'
        ));

        $this->expectException(ProviderApiException::class);

        ($this->gateway($api))->listPage(
            TestConfiguration::participant(),
            new ListWebhooksRequest(null, PageSize::fromInt(100))
        );
    }

    public function testListPageRejectsMalformedWebhookEvents(): void
    {
        $api = $this->createMock(ListWebhooksEndpoint::class);
        $api->method('send')->willThrowException(new InvalidProviderResponseException(
            'Webhook list response contains a non-array item.'
        ));

        $this->expectException(ProviderApiException::class);

        ($this->gateway($api))->listPage(
            TestConfiguration::participant(),
            new ListWebhooksRequest(null, PageSize::fromInt(100))
        );
    }

    public function testUpdateUsesUpdateEndpointAndReturnsWebhook(): void
    {
        $request = new UpdateWebhookRequest(
            WebhookIdentifier::fromString('synthetic-webhook'),
            WebhookUpdate::empty()->withStatus(WebhookStatus::fromString('DISABLED'))
        );
        $api = $this->createMock(UpdateWebhookEndpoint::class);
        $api->expects($this->once())
            ->method('send')
            ->with($this->equalTo(TestConfiguration::participant()), $this->equalTo($request))
            ->willReturn(WebhookFactory::fromArray(
                ApiFixtures::webhook('synthetic-webhook', ['status' => 'DISABLED'])
            ));

        $result = ($this->gateway($api))->update(
            TestConfiguration::participant(),
            $request
        );

        $this->assertInstanceOf(Webhook::class, $result);
        $this->assertSame('synthetic-webhook', $result->id()->value());
        $this->assertSame('DISABLED', $result->status()->value());
    }

    public function testUpdatePassesNullChangesToClearNullableProviderValues(): void
    {
        $request = new UpdateWebhookRequest(
            WebhookIdentifier::fromString('synthetic-webhook'),
            WebhookUpdate::empty()->withDescription(null)
        );
        $api = $this->createMock(UpdateWebhookEndpoint::class);
        $api->expects($this->once())
            ->method('send')
            ->with($this->equalTo(TestConfiguration::participant()), $this->equalTo($request))
            ->willReturn(WebhookFactory::fromArray(ApiFixtures::webhook('synthetic-webhook')));

        ($this->gateway($api))->update(TestConfiguration::participant(), $request);
    }

    public function testUpdateWithMalformedResponseThrows(): void
    {
        $api = $this->createMock(UpdateWebhookEndpoint::class);
        $api->method('send')->willThrowException(new InvalidProviderResponseException('Webhook data is malformed.'));

        $this->expectException(ProviderApiException::class);

        ($this->gateway($api))->update(
            TestConfiguration::participant(),
            new UpdateWebhookRequest(
                WebhookIdentifier::fromString('synthetic-webhook'),
                WebhookUpdate::empty()->withStatus(WebhookStatus::fromString('DISABLED'))
            )
        );
    }

    public function testDeleteTreatsProviderNotFoundAsAlreadyRemoved(): void
    {
        $request = new DeleteWebhookRequest(WebhookIdentifier::fromString('synthetic-webhook'));
        $api     = $this->createMock(DeleteWebhookEndpoint::class);
        $api->expects($this->once())
            ->method('send')
            ->with($this->equalTo(TestConfiguration::participant()), $this->equalTo($request->webhookId()))
            ->willThrowException(new ProviderErrorResponseException(404));

        $this->assertFalse(($this->gateway($api))->delete(
            TestConfiguration::participant(),
            $request
        ));
    }

    public function testDeletePropagatesOtherProviderErrors(): void
    {
        $api   = $this->createMock(DeleteWebhookEndpoint::class);
        $error = new ProviderErrorResponseException(503);
        $api->expects($this->once())->method('send')->willThrowException($error);

        try {
            ($this->gateway($api))->delete(
                TestConfiguration::participant(),
                new DeleteWebhookRequest(WebhookIdentifier::fromString('synthetic-webhook'))
            );
            $this->fail('Non-404 provider errors must be propagated.');
        } catch (ProviderErrorResponseException $exception) {
            $this->assertSame($error, $exception);
            $this->assertSame(503, $exception->httpStatusCode()->value());
        }
    }

    /**
     * Builds the gateway with the given endpoint mock; the other endpoints are inert mocks.
     *
     * @param RegisterWebhookEndpoint|UpdateWebhookEndpoint|DeleteWebhookEndpoint|
     *        GetWebhookEndpoint|ListWebhooksEndpoint $endpoint Endpoint mock to inject; mocks are created for the
     *                                                           other registry operations.
     */
    private function gateway($endpoint): PeppolGatewayWebhookRegistry
    {
        return new PeppolGatewayWebhookRegistry(
            $endpoint instanceof RegisterWebhookEndpoint
                ? $endpoint : $this->createMock(RegisterWebhookEndpoint::class),
            $endpoint instanceof UpdateWebhookEndpoint
                ? $endpoint : $this->createMock(UpdateWebhookEndpoint::class),
            $endpoint instanceof DeleteWebhookEndpoint
                ? $endpoint : $this->createMock(DeleteWebhookEndpoint::class),
            $endpoint instanceof GetWebhookEndpoint
                ? $endpoint : $this->createMock(GetWebhookEndpoint::class),
            $endpoint instanceof ListWebhooksEndpoint
                ? $endpoint : $this->createMock(ListWebhooksEndpoint::class)
        );
    }
}
