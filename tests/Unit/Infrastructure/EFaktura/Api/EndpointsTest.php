<?php

namespace DreaM1\EfakturaConnector\Tests\Unit\Infrastructure\EFaktura\Api;

use DreaM1\EfakturaConnector\Core\Configuration\OAuthClientId;
use DreaM1\EfakturaConnector\Core\Configuration\OAuthClientSecret;
use DreaM1\EfakturaConnector\Core\Configuration\OAuthScope;
use DreaM1\EfakturaConnector\Core\Connection\CheckReceivingCapabilityRequest;
use DreaM1\EfakturaConnector\Core\Connection\Health\ParticipantProfile;
use DreaM1\EfakturaConnector\Core\Documents\DocumentRequestContext;
use DreaM1\EfakturaConnector\Core\Documents\DocumentSubmissionResult;
use DreaM1\EfakturaConnector\Core\Documents\IdempotencyKey;
use DreaM1\EfakturaConnector\Core\Documents\ListReceivedDocumentsRequest;
use DreaM1\EfakturaConnector\Core\Documents\OutboundDocumentPayload;
use DreaM1\EfakturaConnector\Core\Documents\ReceivedDocumentPage;
use DreaM1\EfakturaConnector\Core\Documents\ReceivedDocumentPayload;
use DreaM1\EfakturaConnector\Core\Documents\ReceivedDocumentReference;
use DreaM1\EfakturaConnector\Core\Documents\SendDocumentRequest;
use DreaM1\EfakturaConnector\Core\Documents\SentDocumentReference;
use DreaM1\EfakturaConnector\Core\Documents\Sha256Checksum;
use DreaM1\EfakturaConnector\Core\Documents\ValidateDocumentRequest;
use DreaM1\EfakturaConnector\Core\Exceptions\RequestEncodingException;
use DreaM1\EfakturaConnector\Core\Pagination\PageSize;
use DreaM1\EfakturaConnector\Core\Pagination\PageToken;
use DreaM1\EfakturaConnector\Core\Webhooks\CreateWebhookRequest;
use DreaM1\EfakturaConnector\Core\Webhooks\ListWebhooksRequest;
use DreaM1\EfakturaConnector\Core\Webhooks\UpdateWebhookRequest;
use DreaM1\EfakturaConnector\Core\Webhooks\Webhook;
use DreaM1\EfakturaConnector\Core\Webhooks\WebhookRegistrationResponse;
use DreaM1\EfakturaConnector\Core\Webhooks\WebhookRegistryPage;
use DreaM1\EfakturaConnector\Core\Webhooks\WebhookUpdate;
use DreaM1\EfakturaConnector\Core\Webhooks\WebhookUrl;
use DreaM1\EfakturaConnector\Domain\EFaktura\MessageLevelStatusIdentifier;
use DreaM1\EfakturaConnector\Domain\EFaktura\Participant;
use DreaM1\EfakturaConnector\Domain\EFaktura\PeppolDocumentTypeIdentifier;
use DreaM1\EfakturaConnector\Domain\EFaktura\PeppolProcessIdentifier;
use DreaM1\EfakturaConnector\Domain\EFaktura\ProviderDocumentId;
use DreaM1\EfakturaConnector\Domain\EFaktura\ProviderDocumentStatus;
use DreaM1\EfakturaConnector\Domain\EFaktura\SubmittedDocumentId;
use DreaM1\EfakturaConnector\Domain\EFaktura\Webhooks\WebhookEventType;
use DreaM1\EfakturaConnector\Domain\EFaktura\Webhooks\WebhookIdentifier;
use DreaM1\EfakturaConnector\Domain\EFaktura\Webhooks\WebhookStatus;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\ApiConnector;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Authentication\AuthenticatedRequestSender;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Authentication\OAuthAccessToken;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Authentication\OAuthTokenRequest;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Authentication\SapiToken;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Authentication\SapiTokenProvider;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Endpoints\PeppolGateway\CheckReceivingCapabilityEndpoint;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Endpoints\PeppolGateway\DeleteWebhookEndpoint;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Endpoints\PeppolGateway\GetMessageLevelStatusContentEndpoint;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Endpoints\PeppolGateway\GetParticipantProfileEndpoint;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Endpoints\PeppolGateway\GetSentDocumentEndpoint;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Endpoints\PeppolGateway\GetWebhookEndpoint;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Endpoints\PeppolGateway\ListMessageLevelStatusesEndpoint;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Endpoints\PeppolGateway\ListWebhooksEndpoint;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Endpoints\PeppolGateway\RegisterWebhookEndpoint;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Endpoints\PeppolGateway\UpdateWebhookEndpoint;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Endpoints\PeppolGateway\ValidateDocumentEndpoint;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Endpoints\Sapi\AcknowledgeReceivedDocumentEndpoint;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Endpoints\Sapi\GetReceivedDocumentEndpoint;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Endpoints\Sapi\GetTokenStatusEndpoint;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Endpoints\Sapi\ListReceivedDocumentsEndpoint;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Endpoints\Sapi\RequestTokenEndpoint;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Endpoints\Sapi\SendDocumentEndpoint;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Response\Sapi\ReceivedDocumentAcknowledgementFactory;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Transport\HttpExchangeRequest;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Transport\HttpExchangeResult;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Transport\HttpTransportInterface;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Http\Protocol\HttpHeaderLine;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Http\Protocol\MediaType;
use DreaM1\EfakturaConnector\Support\Timestamp;
use DreaM1\EfakturaConnector\Tests\Support\ApiFixtures;
use DreaM1\EfakturaConnector\Tests\Support\OpenApiContract;
use DreaM1\EfakturaConnector\Tests\Support\RecordingTransport;
use DreaM1\EfakturaConnector\Tests\Support\TestConfiguration;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/** Checks endpoint request contracts, HTTP routes, authentication and typed response mappings. */
class EndpointsTest extends TestCase
{
    private const PARTICIPANT_ID     = '0245:2012345676';
    private const PARTICIPANT_HEADER = 'X-Peppol-Participant-Id: ' . self::PARTICIPANT_ID;

    // ── SAPI ─────────────────────────────────────────────────────────────────

    public function testTokenEndpointPostsCredentialsWithoutAuthentication(): void
    {
        $transport = RecordingTransport::returning(ApiFixtures::jsonExchange(ApiFixtures::token()));

        $response = (new RequestTokenEndpoint($transport))->send(
            new OAuthTokenRequest(
                OAuthClientId::fromString('client-1'),
                OAuthClientSecret::fromString('secret-1'),
                OAuthScope::fromString('document:send')
            )
        );

        $this->assertSame('synthetic-access-token', $response->accessToken->reveal());
        $request = $transport->lastRequest();
        $this->assertRequest($request, 'POST', '/sapi/auth/token', [], true);
        $this->assertSame([
            'client_id'     => 'client-1',
            'client_secret' => 'secret-1',
            'grant_type'    => 'client_credentials',
            'scope'         => 'document:send',
        ], $this->jsonBody($request));
    }

    public function testTokenStatusEndpoint(): void
    {
        $transport = RecordingTransport::returning(ApiFixtures::jsonExchange(ApiFixtures::tokenStatus()));

        $response = (new GetTokenStatusEndpoint($this->connector($transport)))->send();

        $this->assertTrue($response->valid());
        $this->assertRequest($transport->lastRequest(), 'GET', '/sapi/auth/token/status', [], true);
    }

    public function testSendDocumentCarriesIdempotencyKeyAndSchemaBodyAndIsNeverReplayed(): void
    {
        $transport = RecordingTransport::returning(ApiFixtures::jsonExchange(ApiFixtures::sendDocument('doc-1'), 202));

        $response = (new SendDocumentEndpoint($this->connector($transport)))->send(
            new SendDocumentRequest(
                Participant::parse(self::PARTICIPANT_ID),
                $this->outboundPayload(),
                IdempotencyKey::fromString('00000000-0000-4000-8000-000000000001')
            )
        );

        $this->assertInstanceOf(DocumentSubmissionResult::class, $response);
        $this->assertSame('doc-1', $response->providerDocumentId()->value());
        $request = $transport->lastRequest();
        $this->assertRequest(
            $request,
            'POST',
            '/sapi/document/send',
            ['Idempotency-Key: 00000000-0000-4000-8000-000000000001', self::PARTICIPANT_HEADER],
            false
        );
        $body = $this->jsonBody($request);
        $this->assertSame(['metadata', 'payload', 'payloadFormat', 'payloadEncoding', 'checksum'], array_keys($body));
        $this->assertSame('XML', $body['payloadFormat']);
    }

    public function testReceivedDocumentListEncodesPaginationAndStatusFilter(): void
    {
        $transport = RecordingTransport::returning(
            ApiFixtures::jsonExchange(ApiFixtures::receivedDocumentList(['doc-1']))
        );
        $endpoint = new ListReceivedDocumentsEndpoint($this->connector($transport));

        $response = $endpoint->send(
            new ListReceivedDocumentsRequest(
                TestConfiguration::participant(),
                PageToken::fromString('next page'),
                PageSize::fromInt(10)
            )
        );
        $endpoint->send(new ListReceivedDocumentsRequest(
            TestConfiguration::participant(),
            null,
            null,
            ProviderDocumentStatus::fromString(ProviderDocumentStatus::RECEIVED)
        ));

        $this->assertInstanceOf(ReceivedDocumentPage::class, $response);
        $this->assertSame('doc-1', $response->documents()[0]->documentId()->value());
        [$paged, $filtered] = $transport->requests();
        $this->assertRequest(
            $paged,
            'GET',
            '/sapi/document/receive?pageToken=next%20page&limit=10',
            [self::PARTICIPANT_HEADER],
            true
        );
        $this->assertSame('/sapi/document/receive?status=RECEIVED', $filtered->path());
        $this->assertOpenApiRequest($filtered);
    }

    public function testReceivedDocumentListAllowsLongPaginationTokens(): void
    {
        $token     = str_repeat('x', 5000);
        $transport = RecordingTransport::returning(ApiFixtures::jsonExchange(ApiFixtures::receivedDocumentList([])));

        (new ListReceivedDocumentsEndpoint($this->connector($transport)))->send(
            new ListReceivedDocumentsRequest(
                TestConfiguration::participant(),
                PageToken::fromString($token),
                PageSize::fromInt(10)
            )
        );

        $this->assertSame('/sapi/document/receive?pageToken=' . $token . '&limit=10', $transport->lastRequest()->path());
        $this->assertOpenApiRequest($transport->lastRequest());
    }

    public function testRejectsUndocumentedReceivedDocumentStatusFilter(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new ListReceivedDocumentsRequest(
            TestConfiguration::participant(),
            null,
            PageSize::fromInt(10),
            ProviderDocumentStatus::fromString(ProviderDocumentStatus::ACCEPTED)
        );
    }

    public function testReceivedDocumentDetailCarriesDocumentContext(): void
    {
        $transport = RecordingTransport::returning(
            ApiFixtures::jsonExchange(ApiFixtures::receivedDocumentDetail('<Invoice/>'))
        );
        $context = new DocumentRequestContext('doc-1', null);

        $response = (new GetReceivedDocumentEndpoint($this->connector($transport)))->send(
            new ReceivedDocumentReference(
                TestConfiguration::participant(),
                ProviderDocumentId::fromString('provider/1'),
                $context
            )
        );

        $this->assertInstanceOf(ReceivedDocumentPayload::class, $response);
        $this->assertSame('<Invoice/>', $response->xml());
        $request = $transport->lastRequest();
        $this->assertRequest($request, 'GET', '/sapi/document/receive/provider%2F1', [self::PARTICIPANT_HEADER], true);
        $this->assertSame($context->toArray(), $request->context());
    }

    public function testAcknowledgeReceivedDocument(): void
    {
        $transport = RecordingTransport::returning(ApiFixtures::jsonExchange(ApiFixtures::acknowledge('provider-1')));
        $context   = new DocumentRequestContext('provider-1', null);

        $response = (new AcknowledgeReceivedDocumentEndpoint($this->connector($transport)))
            ->send(new ReceivedDocumentReference(
                TestConfiguration::participant(),
                ProviderDocumentId::fromString('provider-1'),
                $context
            ));

        $this->assertSame('provider-1', $response->documentId()->value());
        $this->assertRequest(
            $transport->lastRequest(),
            'POST',
            '/sapi/document/receive/provider-1/acknowledge',
            [self::PARTICIPANT_HEADER],
            true
        );
    }

    public function testAcknowledgeResponseAcceptsStatusesFromTheSapiContract(): void
    {
        $data           = ApiFixtures::acknowledge('provider-1');
        $data['status'] = 'RECEIVED';

        $this->assertSame('RECEIVED', ReceivedDocumentAcknowledgementFactory::fromArray($data)->status()->value());
    }

    // ── Peppol Gateway ───────────────────────────────────────────────────────

    public function testDiscoveryIsARetrySafeLookupWithoutParticipantHeader(): void
    {
        $transport = RecordingTransport::returning(ApiFixtures::jsonExchange(ApiFixtures::discovery('0245:receiver')));

        $response = (new CheckReceivingCapabilityEndpoint($this->connector($transport)))
            ->send(new CheckReceivingCapabilityRequest(
                Participant::parse('0245:receiver'),
                PeppolDocumentTypeIdentifier::fromString('doc-type')
            ));

        $this->assertTrue($response->receives);
        $request = $transport->lastRequest();
        $this->assertRequest($request, 'POST', '/v1/discovery/receives', [], true);
        $this->assertSame(
            ['participantId' => '0245:receiver', 'documentTypeId' => 'doc-type'],
            $this->jsonBody($request)
        );
    }

    public function testDiscoveryRequestRejectsRemovedParticipantScheme(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new CheckReceivingCapabilityEndpoint($this->connector(RecordingTransport::returning(
            ApiFixtures::jsonExchange(ApiFixtures::discovery('0037:sample'))
        ))))->send(new CheckReceivingCapabilityRequest(
            Participant::parse('0037:sample'),
            PeppolDocumentTypeIdentifier::fromString('doc-type')
        ));
    }

    public function testParticipantProfile(): void
    {
        $transport = RecordingTransport::returning(
            ApiFixtures::jsonExchange(ApiFixtures::participant('0088:synthetic-id', true, [ApiFixtures::capability()]))
        );

        $response = (new GetParticipantProfileEndpoint($this->connector($transport)))
            ->send(Participant::parse('0088:synthetic-id'));

        $this->assertInstanceOf(ParticipantProfile::class, $response);
        $this->assertSame('0088:synthetic-id', $response->participant()->identifier());
        $this->assertRequest($transport->lastRequest(), 'GET', '/v1/participants/0088%3Asynthetic-id', [], true);
    }

    public function testParticipantProfileRequestRejectsRemovedScheme(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new GetParticipantProfileEndpoint($this->connector(RecordingTransport::returning(
            ApiFixtures::jsonExchange(ApiFixtures::participant('0037:sample', true, []))
        ))))->send(Participant::parse('0037:sample'));
    }

    public function testDocumentValidationSendsTheSendDocumentSchema(): void
    {
        $transport = RecordingTransport::returning(ApiFixtures::jsonExchange(['valid' => true]));

        $response = (new ValidateDocumentEndpoint($this->connector($transport)))->send(
            new ValidateDocumentRequest(
                Participant::parse(self::PARTICIPANT_ID),
                $this->outboundPayload()
            )
        );

        $this->assertTrue($response);
        $request = $transport->lastRequest();
        $this->assertRequest($request, 'POST', '/v1/validate-document', [self::PARTICIPANT_HEADER], true);
        $this->assertSame([
            'metadata' => [
                'documentId'            => 'INV-SYNTHETIC-1',
                'documentTypeId'        => 'synthetic-document-type',
                'processId'             => 'synthetic-process',
                'senderParticipantId'   => '0245:2012345676',
                'receiverParticipantId' => '0245:2012345687',
                'creationDateTime'      => '2026-11-15T09:30:00Z',
            ],
            'payload'         => '<Invoice>synthetic</Invoice>',
            'payloadFormat'   => 'XML',
            'payloadEncoding' => 'UTF-8',
            'checksum'        => hash('sha256', '<Invoice>synthetic</Invoice>'),
        ], $this->jsonBody($request));
    }

    public function testSentDocumentAndMessageLevelStatuses(): void
    {
        $transport = new RecordingTransport(function (HttpExchangeRequest $request): HttpExchangeResult {
            return $request->path() === '/v1/sent-documents/document-1'
                ? ApiFixtures::jsonExchange(ApiFixtures::sentDocument('RECEIVED_BY_GATEWAY'))
                : ApiFixtures::jsonExchange(['items' => [ApiFixtures::messageLevelStatus()]]);
        });
        $connector = $this->connector($transport);
        $context   = new DocumentRequestContext('doc-1', 'INV-1');

        $reference = new SentDocumentReference(
            TestConfiguration::participant(),
            ProviderDocumentId::fromString('document-1'),
            $context
        );
        $sent = (new GetSentDocumentEndpoint($connector))->send(
            $reference
        );
        $statuses = (new ListMessageLevelStatusesEndpoint($connector))->send(
            $reference
        );

        $this->assertSame('RECEIVED_BY_GATEWAY', $sent->status()->value());
        $this->assertCount(1, $statuses->items());
        [$sentRequest, $statusesRequest] = $transport->requests();
        $this->assertRequest($sentRequest, 'GET', '/v1/sent-documents/document-1', [self::PARTICIPANT_HEADER], true);
        $this->assertRequest(
            $statusesRequest,
            'GET',
            '/v1/sent-documents/document-1/message-level-statuses',
            [self::PARTICIPANT_HEADER],
            true
        );
        $this->assertSame($context->toArray(), $statusesRequest->context());
    }

    public function testMessageLevelStatusContentAcceptsXml(): void
    {
        $transport = RecordingTransport::returning(ApiFixtures::xmlExchange('<ApplicationResponse/>'));

        $response = (new GetMessageLevelStatusContentEndpoint($this->connector($transport)))->send(
            new SentDocumentReference(
                TestConfiguration::participant(),
                ProviderDocumentId::fromString('document-1'),
                new DocumentRequestContext('document-1', null)
            ),
            MessageLevelStatusIdentifier::fromString('status-1')
        );

        $this->assertSame('<ApplicationResponse/>', $response->xml());
        $request = $transport->lastRequest();
        $this->assertOpenApiRequest($request);
        $this->assertSame(
            '/v1/sent-documents/document-1/message-level-statuses/status-1/content',
            $request->path()
        );
        $this->assertSame(MediaType::APPLICATION_XML, $request->acceptHeader());
    }

    public function testWebhookEndpoints(): void
    {
        $transport = new RecordingTransport(function (HttpExchangeRequest $request): HttpExchangeResult {
            switch ($request->httpMethod()->value() . ' ' . $request->path()) {
                case 'POST /v1/webhooks':
                    return ApiFixtures::jsonExchange(ApiFixtures::webhookCreate('webhook-1'), 201);
                case 'GET /v1/webhooks?pageToken=next%20page&limit=10':
                case 'GET /v1/webhooks':
                    return ApiFixtures::jsonExchange(ApiFixtures::webhookList([ApiFixtures::webhook()]));
                case 'DELETE /v1/webhooks/webhook-1':
                    return new HttpExchangeResult(204, '', [], microtime(true));
                default:
                    return ApiFixtures::jsonExchange(ApiFixtures::webhook('webhook-1'));
            }
        });
        $connector = $this->connector($transport);

        $participant = TestConfiguration::participant();
        $created     = (new RegisterWebhookEndpoint($connector))->send(
            $participant,
            new CreateWebhookRequest(
                WebhookUrl::fromString('https://example.invalid/wh'),
                [WebhookEventType::fromString(
                    WebhookEventType::INBOX_DOCUMENT_RECEIVED
                )],
                'Test'
            )
        );
        $list = (new ListWebhooksEndpoint($connector))->send(
            $participant,
            new ListWebhooksRequest(
                PageToken::fromString('next page'),
                PageSize::fromInt(10)
            )
        );
        (new ListWebhooksEndpoint($connector))->send($participant, new ListWebhooksRequest());
        $fetched = (new GetWebhookEndpoint($connector))->send(
            $participant,
            WebhookIdentifier::fromString('webhook-1')
        );
        $updated = (new UpdateWebhookEndpoint($connector))->send(
            $participant,
            new UpdateWebhookRequest(
                WebhookIdentifier::fromString('webhook-1'),
                WebhookUpdate::empty()->withStatus(WebhookStatus::fromString('DISABLED'))
            )
        );
        (new DeleteWebhookEndpoint($connector))->send($participant, WebhookIdentifier::fromString('webhook-1'));

        $this->assertInstanceOf(WebhookRegistrationResponse::class, $created);
        $this->assertSame('synthetic-signing-secret', $created->signingSecret()->reveal());
        $this->assertInstanceOf(WebhookRegistryPage::class, $list);
        $this->assertInstanceOf(Webhook::class, $list->webhooks()->items()[0]);
        $this->assertInstanceOf(Webhook::class, $fetched);
        $this->assertInstanceOf(Webhook::class, $updated);
        $this->assertSame('webhook-1', $fetched->id()->value());
        $this->assertSame('webhook-1', $updated->id()->value());

        [$create, $paged, $unpaged, $get, $update, $delete] = $transport->requests();
        $this->assertRequest($create, 'POST', '/v1/webhooks', [self::PARTICIPANT_HEADER], false);
        $this->assertSame(
            ['url' => 'https://example.invalid/wh', 'events' => ['inbox_document.received'], 'description' => 'Test'],
            $this->jsonBody($create)
        );
        $this->assertRequest(
            $paged,
            'GET',
            '/v1/webhooks?pageToken=next%20page&limit=10',
            [self::PARTICIPANT_HEADER],
            true
        );
        $this->assertSame('/v1/webhooks', $unpaged->path());
        $this->assertOpenApiRequest($unpaged);
        $this->assertRequest($get, 'GET', '/v1/webhooks/webhook-1', [self::PARTICIPANT_HEADER], true);
        $this->assertRequest($update, 'PATCH', '/v1/webhooks/webhook-1', [self::PARTICIPANT_HEADER], false);
        $this->assertSame(['status' => 'DISABLED'], $this->jsonBody($update));
        $this->assertRequest($delete, 'DELETE', '/v1/webhooks/webhook-1', [self::PARTICIPANT_HEADER], false);
    }

    // ── Cross-cutting ────────────────────────────────────────────────────────

    public function testEndpointsDeclareConcreteResultTypes(): void
    {
        $endpoints = [
            DeleteWebhookEndpoint::class,
            ValidateDocumentEndpoint::class,
            GetWebhookEndpoint::class,
            ListWebhooksEndpoint::class,
            GetMessageLevelStatusContentEndpoint::class,
            ListMessageLevelStatusesEndpoint::class,
            GetParticipantProfileEndpoint::class,
            CheckReceivingCapabilityEndpoint::class,
            RegisterWebhookEndpoint::class,
            GetSentDocumentEndpoint::class,
            UpdateWebhookEndpoint::class,
            AcknowledgeReceivedDocumentEndpoint::class,
            GetReceivedDocumentEndpoint::class,
            ListReceivedDocumentsEndpoint::class,
            SendDocumentEndpoint::class,
            RequestTokenEndpoint::class,
            GetTokenStatusEndpoint::class,
        ];

        foreach ($endpoints as $endpoint) {
            $returnType = (new ReflectionMethod($endpoint, 'send'))->getReturnType();
            $this->assertNotNull($returnType, $endpoint);
            $this->assertTrue(
                in_array($returnType->getName(), ['void', 'bool'], true) || class_exists($returnType->getName()),
                $endpoint . ' must return a typed model, bool or void.'
            );
        }
    }

    public function testRequestsAreAuthenticatedWhenSentThroughTheAuthenticatedTransport(): void
    {
        $transport     = RecordingTransport::returning(ApiFixtures::jsonExchange(ApiFixtures::tokenStatus()));
        $tokenProvider = $this->createMock(SapiTokenProvider::class);
        $tokenProvider->method('getToken')->willReturn(
            new SapiToken(
                OAuthAccessToken::fromString('synthetic-token'),
                time() + 300
            )
        );

        (new GetTokenStatusEndpoint(
            new ApiConnector(new AuthenticatedRequestSender($transport, $tokenProvider))
        ))->send();

        $this->assertSame(
            'Authorization: Bearer synthetic-token',
            $transport->lastRequest()->headers()->all()[0]->toString()
        );
        $this->assertOpenApiRequest($transport->lastRequest());
    }

    public function testUnencodableBodyFailsBeforeAnyHttpCallOrToken(): void
    {
        $transport = $this->createMock(HttpTransportInterface::class);
        $transport->expects($this->never())->method('send');
        $payload = new OutboundDocumentPayload(
            SubmittedDocumentId::fromString('INV-SYNTHETIC-1'),
            PeppolDocumentTypeIdentifier::fromString('synthetic-document-type'),
            PeppolProcessIdentifier::fromString('synthetic-process'),
            Participant::parse('0245:2012345676'),
            Participant::parse('0245:2012345687'),
            Timestamp::fromIso8601('2026-11-15T09:30:00Z'),
            "<Invoice>\xB1</Invoice>",
            null
        );

        $this->expectException(RequestEncodingException::class);

        (new SendDocumentEndpoint($this->connector($transport)))->send(
            new SendDocumentRequest(
                Participant::parse(self::PARTICIPANT_ID),
                $payload,
                IdempotencyKey::fromString('00000000-0000-4000-8000-000000000001')
            )
        );
    }

    public function testSentDocumentReferenceRejectsEmptyIdentifier(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new SentDocumentReference(
            TestConfiguration::participant(),
            ProviderDocumentId::fromString(' '),
            new DocumentRequestContext('document-1', null)
        );
    }

    /**
     * Checks the assembled request fields and verifies them against the OpenAPI contract.
     *
     * @param string[] $headers Expected serialized request headers, in order.
     */
    private function assertRequest(
        HttpExchangeRequest $request,
        string $method,
        string $path,
        array $headers,
        bool $retrySafe
    ): void {
        $this->assertSame($method, $request->httpMethod()->value());
        $this->assertSame($path, $request->path());
        $this->assertSame($headers, array_map(
            static function (HttpHeaderLine $header): string {
                return $header->toString();
            },
            $request->headers()->all()
        ));
        $this->assertSame($retrySafe, $request->isRetrySafe(), 'retry safety of ' . $method . ' ' . $path);
        $this->assertSame(MediaType::APPLICATION_JSON, $request->acceptHeader());
        $this->assertOpenApiRequest($request);
    }

    /** Verifies that the request matches the documented route and parameter contract. */
    private function assertOpenApiRequest(HttpExchangeRequest $request): void
    {
        $errors = (new OpenApiContract())->validateRequest($request);
        $this->assertSame([], $errors, sprintf(
            'Request %s %s must match OpenAPI: %s',
            $request->httpMethod()->value(),
            $request->path(),
            implode('; ', $errors)
        ));
    }

    /** @return array<string, mixed> Decoded JSON request body. */
    private function jsonBody(HttpExchangeRequest $request): array
    {
        return json_decode((string) $request->body(), true);
    }

    private function connector(HttpTransportInterface $transport): ApiConnector
    {
        return new ApiConnector($transport);
    }

    private function outboundPayload(): OutboundDocumentPayload
    {
        return new OutboundDocumentPayload(
            SubmittedDocumentId::fromString('INV-SYNTHETIC-1'),
            PeppolDocumentTypeIdentifier::fromString('synthetic-document-type'),
            PeppolProcessIdentifier::fromString('synthetic-process'),
            Participant::parse('0245:2012345676'),
            Participant::parse('0245:2012345687'),
            Timestamp::fromIso8601('2026-11-15T09:30:00Z'),
            '<Invoice>synthetic</Invoice>',
            Sha256Checksum::fromContent('<Invoice>synthetic</Invoice>')
        );
    }
}
