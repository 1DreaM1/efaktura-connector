<?php

namespace DreaM1\EfakturaConnector\Tests\Unit\Infrastructure\EFaktura\Api;

use DreaM1\EfakturaConnector\Core\Configuration\OAuthClientId;
use DreaM1\EfakturaConnector\Core\Configuration\OAuthClientSecret;
use DreaM1\EfakturaConnector\Core\Configuration\OAuthScope;
use DreaM1\EfakturaConnector\Core\Connection\CheckReceivingCapabilityRequest;
use DreaM1\EfakturaConnector\Core\Documents\IdempotencyKey;
use DreaM1\EfakturaConnector\Core\Documents\ListReceivedDocumentsRequest;
use DreaM1\EfakturaConnector\Core\Documents\OutboundDocumentPayload;
use DreaM1\EfakturaConnector\Core\Documents\SendDocumentRequest;
use DreaM1\EfakturaConnector\Core\Documents\Sha256Checksum;
use DreaM1\EfakturaConnector\Core\Documents\ValidateDocumentRequest;
use DreaM1\EfakturaConnector\Core\Pagination\PageSize;
use DreaM1\EfakturaConnector\Core\Pagination\PageToken;
use DreaM1\EfakturaConnector\Core\Webhooks\CreateWebhookRequest;
use DreaM1\EfakturaConnector\Core\Webhooks\DeleteWebhookRequest;
use DreaM1\EfakturaConnector\Core\Webhooks\ListWebhooksRequest;
use DreaM1\EfakturaConnector\Core\Webhooks\UpdateWebhookRequest;
use DreaM1\EfakturaConnector\Core\Webhooks\WebhookUpdate;
use DreaM1\EfakturaConnector\Core\Webhooks\WebhookUrl;
use DreaM1\EfakturaConnector\Domain\EFaktura\Participant;
use DreaM1\EfakturaConnector\Domain\EFaktura\PeppolDocumentTypeIdentifier;
use DreaM1\EfakturaConnector\Domain\EFaktura\PeppolProcessIdentifier;
use DreaM1\EfakturaConnector\Domain\EFaktura\ProviderDocumentStatus;
use DreaM1\EfakturaConnector\Domain\EFaktura\SubmittedDocumentId;
use DreaM1\EfakturaConnector\Domain\EFaktura\Webhooks\WebhookEventType;
use DreaM1\EfakturaConnector\Domain\EFaktura\Webhooks\WebhookIdentifier;
use DreaM1\EfakturaConnector\Domain\EFaktura\Webhooks\WebhookStatus;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Authentication\OAuthTokenRequest;
use DreaM1\EfakturaConnector\Support\Timestamp;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/** Tests public Core operation inputs and the internal OAuth request. */
class RequestContractsTest extends TestCase
{
    public function testOAuthTokenRequestOmitsNullScopeAndIncludesProvidedScope(): void
    {
        $clientId = OAuthClientId::fromString('client');
        $secret   = OAuthClientSecret::fromString('secret');
        $scope    = OAuthScope::fromString('sapi:send');

        $withoutScope = new OAuthTokenRequest($clientId, $secret);
        $withScope    = new OAuthTokenRequest($clientId, $secret, $scope);

        $this->assertSame([
            'client_id'     => 'client',
            'client_secret' => 'secret',
            'grant_type'    => 'client_credentials',
        ], $withoutScope->toBody());
        $this->assertSame('sapi:send', $withScope->toBody()['scope']);
        $this->assertSame($clientId, $withScope->clientId());
        $this->assertSame($secret, $withScope->clientSecret());
        $this->assertSame($scope, $withScope->scope());
        $this->assertNull($withoutScope->scope());
    }

    public function testReceivingCapabilityRequestExposesTypedValues(): void
    {
        $participant = Participant::parse('0245:2012345676');
        $document    = PeppolDocumentTypeIdentifier::fromString('invoice-type');
        $process     = PeppolProcessIdentifier::fromString('process-1');
        $request     = new CheckReceivingCapabilityRequest($participant, $document, $process);

        $this->assertSame($participant, $request->participant());
        $this->assertSame($document, $request->documentTypeId());
        $this->assertSame($process, $request->processId());
        $this->assertNull((new CheckReceivingCapabilityRequest($participant, $document))->processId());
    }

    public function testWebhookRequestsValidateAndPreserveTheirTypedValues(): void
    {
        $event  = WebhookEventType::fromString(WebhookEventType::SENT_DOCUMENT_STATUS_CHANGED);
        $create = new CreateWebhookRequest(
            WebhookUrl::fromString('https://example.test/hook'),
            [$event],
            'Synthetic webhook'
        );
        $this->assertSame('https://example.test/hook', $create->url()->value());
        $this->assertSame($event, $create->events()[0]);
        $this->assertSame('Synthetic webhook', $create->description());

        $update = new UpdateWebhookRequest(
            WebhookIdentifier::fromString('hook-1'),
            WebhookUpdate::empty()->withDescription(null)->withStatus(null)->withEvents([])
        );
        $this->assertTrue($update->update()->hasDescription());
        $this->assertNull($update->update()->description());
        $this->assertTrue($update->update()->hasStatus());
        $this->assertNull($update->update()->status());
        $this->assertSame([], $update->update()->events());

        $delete = new DeleteWebhookRequest(WebhookIdentifier::fromString('hook-1'));
        $this->assertSame('hook-1', $delete->webhookId()->value());

        $this->expectException(InvalidArgumentException::class);
        new UpdateWebhookRequest(WebhookIdentifier::fromString('hook-1'), WebhookUpdate::empty());
    }

    public function testWebhookListRequestExposesOptionalPagination(): void
    {
        $bare = new ListWebhooksRequest();
        $this->assertNull($bare->pageToken());
        $this->assertNull($bare->limit());

        $request = new ListWebhooksRequest(PageToken::fromString('cursor'), PageSize::fromInt(10));
        $this->assertSame('cursor', $request->pageToken()->value());
        $this->assertSame(10, $request->limit()->value());
    }

    public function testReceivedDocumentListRequestValidatesProviderFilters(): void
    {
        $participant = Participant::parse('0245:2012345676');
        $request     = new ListReceivedDocumentsRequest(
            $participant,
            PageToken::fromString('cursor'),
            PageSize::fromInt(25),
            ProviderDocumentStatus::fromString(ProviderDocumentStatus::ACKNOWLEDGED)
        );

        $this->assertSame($participant, $request->participant());
        $this->assertSame('cursor', $request->pageToken()->value());
        $this->assertSame(25, $request->limit()->value());
        $this->assertSame(ProviderDocumentStatus::ACKNOWLEDGED, $request->status()->value());

        try {
            new ListReceivedDocumentsRequest(
                $participant,
                null,
                null,
                ProviderDocumentStatus::fromString(ProviderDocumentStatus::ACCEPTED)
            );
            $this->fail('Only received-document statuses are supported as list filters.');
        } catch (InvalidArgumentException $exception) {
            $this->assertNotSame('', $exception->getMessage());
        }

        $this->expectException(InvalidArgumentException::class);
        new ListReceivedDocumentsRequest($participant, null, PageSize::fromInt(101));
    }

    public function testDocumentRequestsKeepParticipantAndPayloadConsistent(): void
    {
        $payload = $this->payload();
        $key     = IdempotencyKey::fromString('00000000-0000-4000-8000-000000000001');
        $sender  = $payload->sender();
        $send    = new SendDocumentRequest($sender, $payload, $key);
        $check   = new ValidateDocumentRequest($sender, $payload);

        $this->assertSame($sender, $send->participant());
        $this->assertSame($payload, $send->payload());
        $this->assertSame($key, $send->idempotencyKey());
        $this->assertSame($sender, $check->participant());
        $this->assertSame($payload, $check->payload());

        $other = Participant::parse('0245:other');
        $this->expectException(InvalidArgumentException::class);
        new SendDocumentRequest($other, $payload, $key);
    }

    private function payload(): OutboundDocumentPayload
    {
        return new OutboundDocumentPayload(
            SubmittedDocumentId::fromString('document-1'),
            PeppolDocumentTypeIdentifier::fromString('document-type'),
            PeppolProcessIdentifier::fromString('process-1'),
            Participant::parse('0245:sender'),
            Participant::parse('0245:receiver'),
            Timestamp::fromIso8601('2026-11-20T14:30:00Z'),
            '<Invoice/>',
            Sha256Checksum::fromContent('<Invoice/>')
        );
    }
}
