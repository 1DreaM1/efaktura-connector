<?php

namespace DreaM1\EfakturaConnector\Tests\Unit\Infrastructure\EFaktura\Api;

use DreaM1\EfakturaConnector\Core\Configuration\OAuthScope;
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
use DreaM1\EfakturaConnector\Core\Documents\Sha256Checksum;
use DreaM1\EfakturaConnector\Core\Exceptions\InvalidProviderResponseException;
use DreaM1\EfakturaConnector\Core\Webhooks\WebhookRegistrationResponse;
use DreaM1\EfakturaConnector\Core\Webhooks\WebhookRegistryPage;
use DreaM1\EfakturaConnector\Domain\EFaktura\Participant;
use DreaM1\EfakturaConnector\Domain\EFaktura\PeppolDocumentTypeIdentifier;
use DreaM1\EfakturaConnector\Domain\EFaktura\PeppolProcessIdentifier;
use DreaM1\EfakturaConnector\Domain\EFaktura\ProviderDocumentId;
use DreaM1\EfakturaConnector\Domain\EFaktura\SubmittedDocumentId;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\ApiConnector;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Endpoints\PeppolGateway\GetParticipantProfileEndpoint;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Endpoints\Sapi\GetReceivedDocumentEndpoint;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Endpoints\Sapi\ListReceivedDocumentsEndpoint;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Endpoints\Sapi\SendDocumentEndpoint;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Response\PeppolGateway\CheckReceivingCapabilityResponseFactory;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Response\PeppolGateway\DocumentValidationVerdictReader;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Response\PeppolGateway\ProviderMessageLevelStatusResponseFactory;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Response\PeppolGateway\SentDocumentStatusFactory;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Response\PeppolGateway\WebhookFactory;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Response\PeppolGateway\WebhookRegistrationResponseFactory;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Response\PeppolGateway\WebhookRegistryPageFactory;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Response\Sapi\ReceivedDocumentAcknowledgementFactory;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Response\Sapi\SapiTokenResponse;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Response\Sapi\TokenStatusFactory;
use DreaM1\EfakturaConnector\Infrastructure\ResourcePath;
use DreaM1\EfakturaConnector\Support\Timestamp;
use DreaM1\EfakturaConnector\Tests\Support\ApiFixtures;
use DreaM1\EfakturaConnector\Tests\Support\RecordingTransport;
use DreaM1\EfakturaConnector\Tests\Support\TestConfiguration;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * Response factories validate OpenAPI schemas: required fields are enforced, optional fields may be absent,
 * nullable fields may also contain null, and every non-null value must have the declared JSON type.
 */

class ApiResponseSchemaTest extends TestCase
{
    public function testSendDocumentResponseRequiresDocumentedFields(): void
    {
        $result = $this->sendDocument(ApiFixtures::sendDocument('provider-1'));
        $this->assertSame('provider-1', $result->providerDocumentId()->value());
        $this->assertSame('ACCEPTED', $result->status()->value());
        $this->assertSame(ApiFixtures::TIMESTAMP, $result->timestamp()->value());
        $this->assertSame(ApiFixtures::TIMESTAMP, $result->receivedAt()->value());

        $rejected = $this->sendDocument(ApiFixtures::sendDocument('provider-2', 'REJECTED'));
        $this->assertSame(ApiFixtures::TIMESTAMP, $rejected->timestamp()->value());
        $this->assertNull($rejected->receivedAt());

        $this->assertSchemaViolation(function (): void {
            $this->sendDocument(['receivedAt' => 'yesterday'] + ApiFixtures::sendDocument('provider-1'));
        }, 'receivedAt');
        $this->assertSchemaViolation(function (): void {
            $this->sendDocument(['receivedAt' => null] + ApiFixtures::sendDocument('provider-1'));
        }, 'receivedAt');

        $acceptedWithoutReceivedAt = ApiFixtures::sendDocument('provider-1');
        unset($acceptedWithoutReceivedAt['receivedAt']);
        $this->assertSchemaViolation(function () use ($acceptedWithoutReceivedAt): void {
            $this->sendDocument($acceptedWithoutReceivedAt);
        }, 'receivedAt');

        $this->assertSchemaViolation(function (): void {
            $this->sendDocument([
                'providerDocumentId' => 'provider-1',
                'status'             => 'ACCEPTED',
                'receivedAt'         => ApiFixtures::TIMESTAMP,
            ]);
        }, 'timestamp');
        $unknown = $this->sendDocument(ApiFixtures::sendDocument('provider-3', 'FUTURE_STATUS'));
        $this->assertSame('FUTURE_STATUS', $unknown->status()->value());
        $this->assertFalse($unknown->status()->isKnown());
        $this->assertNull($unknown->receivedAt());
    }

    public function testReceivedDocumentListPreservesLongPaginationTokens(): void
    {
        $token = str_repeat('x', 5000);
        $page  = (new ListReceivedDocumentsEndpoint(
            $this->connector(ApiFixtures::receivedDocumentList(['doc-1'], $token))
        ))
            ->send(new ListReceivedDocumentsRequest(TestConfiguration::participant()));

        $this->assertInstanceOf(ReceivedDocumentPage::class, $page);
        $this->assertSame($token, $page->nextPageToken()->value());
        $this->assertSame('doc-1', $page->documents()[0]->documentId()->value());
        $this->assertSame(ApiFixtures::PROCESS_ID, $page->documents()[0]->processId()->value());
    }

    public function testReceivedDocumentMetadataRequiresRoutingParticipants(): void
    {
        $this->assertSchemaViolation(function (): void {
            $metadata = ApiFixtures::documentMetadata('doc-1');
            unset($metadata['senderParticipantId']);
            (new ListReceivedDocumentsEndpoint($this->connector(['documents' => [$metadata]])))
                ->send(new ListReceivedDocumentsRequest(TestConfiguration::participant()));
        }, 'senderParticipantId');
    }

    public function testReceivedDocumentMetadataRequiresDocumentTypeAndProcessIds(): void
    {
        $metadata = ApiFixtures::documentMetadata('doc-1');
        unset($metadata['documentTypeId']);

        $this->assertSchemaViolation(function () use ($metadata): void {
            (new ListReceivedDocumentsEndpoint($this->connector(['documents' => [$metadata]])))
                ->send(new ListReceivedDocumentsRequest(TestConfiguration::participant()));
        }, 'documentTypeId');

        $metadata = ApiFixtures::documentMetadata('doc-1');
        unset($metadata['processId']);

        $this->assertSchemaViolation(function () use ($metadata): void {
            (new ListReceivedDocumentsEndpoint($this->connector(['documents' => [$metadata]])))
            ->send(new ListReceivedDocumentsRequest(TestConfiguration::participant()));
        }, 'processId');
    }

    public function testReceivedDocumentMetadataRejectsNullForRequiredSchemaFields(): void
    {
        foreach (['documentTypeId', 'processId', 'creationDateTime'] as $field) {
            $metadata         = ApiFixtures::documentMetadata('doc-1');
            $metadata[$field] = null;

            $this->assertSchemaViolation(function () use ($metadata): void {
                (new ListReceivedDocumentsEndpoint($this->connector(['documents' => [$metadata]])))
                    ->send(new ListReceivedDocumentsRequest(TestConfiguration::participant()));
            }, $field);
        }
    }

    public function testReceivedDocumentDetailRequiresMetadataAndPayload(): void
    {
        $response = (new GetReceivedDocumentEndpoint(
            $this->connector(ApiFixtures::receivedDocumentDetail('<Invoice/>'))
        ))->send(new ReceivedDocumentReference(
            TestConfiguration::participant(),
            ProviderDocumentId::fromString('doc-1'),
            new DocumentRequestContext('doc-1', null)
        ));
        $this->assertInstanceOf(ReceivedDocumentPayload::class, $response);
        $this->assertSame('<Invoice/>', $response->xml());
        $this->assertSame('XML', $response->payloadFormat());

        $this->assertSchemaViolation(function (): void {
            (new GetReceivedDocumentEndpoint($this->connector(['payload' => '<Invoice/>', 'payloadFormat' => 'XML'])))
                ->send(new ReceivedDocumentReference(
                    TestConfiguration::participant(),
                    ProviderDocumentId::fromString('doc-1'),
                    new DocumentRequestContext('doc-1', null)
                ));
        }, 'metadata');
    }

    public function testAcknowledgeResponse(): void
    {
        $response = ReceivedDocumentAcknowledgementFactory::fromArray(ApiFixtures::acknowledge('doc-1'));

        $this->assertSame('doc-1', $response->documentId()->value());
        $this->assertSame('ACKNOWLEDGED', $response->status()->value());

        $unknown = ReceivedDocumentAcknowledgementFactory::fromArray([
            'documentId'           => 'doc-2',
            'status'               => 'FUTURE_RECEIPT_STATUS',
            'acknowledgedDateTime' => ApiFixtures::TIMESTAMP,
        ]);
        $this->assertSame('FUTURE_RECEIPT_STATUS', $unknown->status()->value());
        $this->assertFalse($unknown->status()->isKnown());
    }

    public function testTokenStatusFieldsAreOptional(): void
    {
        $full = TokenStatusFactory::fromArray(ApiFixtures::tokenStatus(false));
        $this->assertFalse($full->valid());
        $this->assertSame(900, $full->expiresInSeconds());

        $this->assertNull(TokenStatusFactory::fromArray([])->valid());

        $futureTokenType = TokenStatusFactory::fromArray(['token_type' => 'future-token-type']);
        $this->assertSame('future-token-type', $futureTokenType->tokenType());
    }

    public function testTokenScopeIsOptionalButNotNullable(): void
    {
        $response = SapiTokenResponse::fromArray(ApiFixtures::token());
        $this->assertInstanceOf(OAuthScope::class, $response->scope);
        $this->assertSame('document:send document:receive', $response->scope->value());
        $this->assertNull(SapiTokenResponse::fromArray([
            'access_token' => 'synthetic-access-token',
            'token_type'   => 'Bearer',
            'expires_in'   => 900,
        ])->scope);

        $this->assertSchemaViolation(function (): void {
            SapiTokenResponse::fromArray(ApiFixtures::token(['scope' => null]));
        }, 'scope');
    }

    public function testSentDocumentResponseHasOnlyOptionalFields(): void
    {
        $response = SentDocumentStatusFactory::fromArray(ApiFixtures::sentDocument('DELIVERY_FAILED', [
            'provider' => ['name' => 'synthetic-ap', 'documentId' => 'ap-1'],
            'error'    => [
                'message' => 'Synthetic delivery failure',
                'details' => [['field' => 'receiver', 'issue' => 'offline']],
            ],
        ]));

        $this->assertSame('DELIVERY_FAILED', $response->status()->value());
        $this->assertSame('synthetic-ap', $response->provider()->name());
        $this->assertSame('offline', $response->error()->details()[0]->issue());

        $invalidDetails = [
            ['field' => 12],
            ['issue' => false],
            ['value' => -1],
            ['field' => null],
        ];

        foreach ($invalidDetails as $invalidDetail) {
            $this->assertSchemaViolation(function () use ($invalidDetail): void {
                SentDocumentStatusFactory::fromArray(ApiFixtures::sentDocument('DELIVERY_FAILED', [
                    'error' => ['details' => [$invalidDetail]],
                ]));
            }, array_key_first($invalidDetail));
        }

        $empty = SentDocumentStatusFactory::fromArray([]);
        $this->assertNull($empty->status());
        $this->assertNull($empty->sender());
        $this->assertNull($empty->error());

        $unicodeWhitespace = SentDocumentStatusFactory::fromArray(ApiFixtures::sentDocument('QUEUED', [
            'senderParticipantId' => "\u{00A0}\u{2003}",
        ]));
        $this->assertNull($unicodeWhitespace->sender());

        $this->assertSchemaViolation(function (): void {
            SentDocumentStatusFactory::fromArray(['senderParticipantId' => null]);
        }, 'senderParticipantId');

        foreach (['documentTypeId', 'processId'] as $field) {
            $this->assertSchemaViolation(function () use ($field): void {
                SentDocumentStatusFactory::fromArray([$field => '  ']);
            }, $field);
        }
    }

    /**
     * Rejects invalid timestamp values in sent-document responses.
     *
     * @param mixed $updatedAt Candidate provider timestamp value.
     * @dataProvider invalidTimestamps
     */
    public function testSentDocumentResponseRejectsInvalidTimestamps($updatedAt): void
    {
        $this->assertSchemaViolation(function () use ($updatedAt): void {
            SentDocumentStatusFactory::fromArray(ApiFixtures::sentDocument('QUEUED', ['updatedAt' => $updatedAt]));
        }, 'updatedAt');
    }

    public function testSentDocumentResponseAcceptsLowercaseRfc3339DateTime(): void
    {
        $response = SentDocumentStatusFactory::fromArray(ApiFixtures::sentDocument('QUEUED', [
            'updatedAt' => '2026-11-20t14:30:00z',
        ]));

        $this->assertSame('2026-11-20t14:30:00z', $response->updatedAt()->value());
    }

    /**
     * Provides timestamps that are invalid for sent-document responses.
     *
     * @return array<array-key, array<array-key, mixed>> Invalid timestamp cases.
     */
    public function invalidTimestamps(): array
    {
        return [
            'array'    => [['unexpected' => 'array']],
            'text'     => ['tomorrow'],
            'calendar' => ['2026-02-30T10:30:00Z'],
        ];
    }

    public function testMessageLevelStatusListSkipsItemsThatDoNotMatchTheSchema(): void
    {
        $response = ProviderMessageLevelStatusResponseFactory::fromArray(['items' => [
            ApiFixtures::messageLevelStatus('status-1'),
            ApiFixtures::messageLevelStatus('status-2', 'RE', ['receivedAt' => ['unexpected' => 'array']]),
            'not-an-object',
        ]]);

        $this->assertCount(1, $response->items());
        $this->assertSame('status-1', $response->items()[0]->identifier()->value());
        $this->assertSame(2, $response->invalidItems());
        $this->assertSame([], ProviderMessageLevelStatusResponseFactory::fromArray([])->items());
    }

    public function testValidateDocumentResponse(): void
    {
        $this->assertTrue(DocumentValidationVerdictReader::fromArray(['valid' => true]));
        $this->assertFalse(DocumentValidationVerdictReader::fromArray(['valid' => false]));

        $this->assertSchemaViolation(static function (): void {
            DocumentValidationVerdictReader::fromArray([]);
        }, 'valid');
        $this->assertSchemaViolation(static function (): void {
            DocumentValidationVerdictReader::fromArray(['valid' => 'yes']);
        }, 'valid');
    }

    public function testDiscoveryResponseWithMatchedCapability(): void
    {
        $response = CheckReceivingCapabilityResponseFactory::fromArray(ApiFixtures::discovery('0245:receiver'));

        $this->assertTrue($response->receives);
        $this->assertSame('https://ap.example.test/as4', $response->endpoint);
        $this->assertSame('peppol-transport-as4-v2_0', $response->capability->transportProfile());

        $unmatched = CheckReceivingCapabilityResponseFactory::fromArray(ApiFixtures::discovery('0245:receiver', false));
        $this->assertNull($unmatched->capability);
        $this->assertNull($unmatched->endpoint);

        $missingCapability = ApiFixtures::discovery('0245:receiver', false);
        unset($missingCapability['capability']);
        $this->assertNull(CheckReceivingCapabilityResponseFactory::fromArray($missingCapability)->capability);

        $nullableCapability                                   = ApiFixtures::discovery('0245:receiver');
        $nullableCapability['capability']['transportProfile'] = null;
        $this->assertNull(CheckReceivingCapabilityResponseFactory::fromArray($nullableCapability)->capability->transportProfile());

        $this->assertSchemaViolation(function (): void {
            CheckReceivingCapabilityResponseFactory::fromArray([
                'participantId' => '0245:receiver',
                'receives'      => true,
                'capability'    => 'not-an-object',
            ]);
        }, 'capability');
    }

    public function testGatewayParticipantParserReportsSchemaContextForMalformedIdentifiers(): void
    {
        try {
            CheckReceivingCapabilityResponseFactory::fromArray([
                'participantId' => 'invalid-participant',
                'receives'      => true,
            ]);
            $this->fail('A malformed participant identifier must be rejected.');
        } catch (InvalidProviderResponseException $exception) {
            $this->assertSame('DiscoveryReceivesResponse', $exception->schema());
            $this->assertSame('participantId', $exception->field());
            $this->assertInstanceOf(InvalidArgumentException::class, $exception->getPrevious());
        }
    }

    public function testLocalDiscoveryCapabilitySchemaIsAnObjectOrNullUnion(): void
    {
        $schemaPath = ResourcePath::join(dirname(__DIR__, 5), 'docs/eFaktura/api/openapi.json');
        $openApi    = json_decode((string) file_get_contents($schemaPath), true);

        $this->assertIsArray($openApi);
        $this->assertSame([
            ['$ref' => '#/components/schemas/ParticipantCapability'],
            ['type' => 'null'],
        ], $openApi['components']['schemas']['DiscoveryReceivesResponse']['properties']['capability']['oneOf']);
    }

    public function testParticipantResponseRequiresCapabilities(): void
    {
        $profile = (new GetParticipantProfileEndpoint(
            $this->connector(array_merge(
                ApiFixtures::participant('0245:p', true, [[
                    'documentTypeId'   => ApiFixtures::DOCUMENT_TYPE_ID,
                    'processId'        => ApiFixtures::PROCESS_ID,
                    'transportProfile' => null,
                ]]),
                ['name' => null]
            ))
        ))->send(Participant::parse('0245:p'));
        $this->assertInstanceOf(ParticipantProfile::class, $profile);
        $this->assertNull($profile->name());
        $this->assertCount(1, $profile->capabilities());
        $this->assertNull(iterator_to_array($profile->capabilities())[0]->transportProfile());

        $this->assertSchemaViolation(function (): void {
            (new GetParticipantProfileEndpoint($this->connector(['participantId' => '0245:p', 'reachable' => true])))
                ->send(Participant::parse('0245:p'));
        }, 'capabilities');
    }

    public function testWebhookFactoriesMapCoreModelsAndValidateProviderSchemas(): void
    {
        $page = WebhookRegistryPageFactory::fromArray(
            ApiFixtures::webhookList([ApiFixtures::webhook('wh-1')], 'next')
        );
        $this->assertInstanceOf(WebhookRegistryPage::class, $page);
        $this->assertSame('wh-1', $page->webhooks()->items()[0]->id()->value());
        $this->assertSame('next', $page->nextPageToken()->value());

        $created = WebhookRegistrationResponseFactory::fromArray(
            ApiFixtures::webhookCreate('wh-2', 'synthetic-secret')
        );
        $this->assertInstanceOf(WebhookRegistrationResponse::class, $created);
        $this->assertSame('synthetic-secret', $created->signingSecret()->reveal());
        $this->assertSame('wh-2', $created->id()->value());
        $this->assertSame('ACTIVE', $created->status()->value());
        $this->assertSame('https://erp.example.test/peppol/webhooks', $created->url()->value());
        $this->assertSame('sent_document.status_changed', $created->events()[0]->value());
        $this->assertSame('inbox_document.received', $created->events()[1]->value());

        $nullableWebhook = WebhookRegistryPageFactory::fromArray(ApiFixtures::webhookList([
            ApiFixtures::webhook('wh-null-description', ['description' => null]),
        ], null));
        $this->assertNull($nullableWebhook->webhooks()->items()[0]->description());
        $this->assertNull($nullableWebhook->nextPageToken());

        $nullableCreated                = ApiFixtures::webhookCreate('wh-null-created', 'synthetic-secret');
        $nullableCreated['description'] = null;
        $this->assertNull(WebhookRegistrationResponseFactory::fromArray($nullableCreated)->description());

        $this->assertSchemaViolation(function (): void {
            WebhookRegistrationResponseFactory::fromArray(ApiFixtures::webhook('wh-3'));
        }, 'signingSecret');
        $futureWebhookPage = WebhookRegistryPageFactory::fromArray(ApiFixtures::webhookList([
            ApiFixtures::webhook('wh-future', [
                'status' => 'PAUSED',
                'events' => ['future.event'],
            ]),
        ]));
        $futureWebhook = $futureWebhookPage->webhooks()->items()[0];
        $this->assertSame('PAUSED', $futureWebhook->status()->value());
        $this->assertFalse($futureWebhook->status()->isKnown());
        $this->assertSame('future.event', $futureWebhook->events()[0]->value());
        $this->assertFalse($futureWebhook->events()[0]->isSupported());

        foreach (['id', 'url', 'events', 'status', 'createdAt', 'updatedAt'] as $field) {
            $this->assertSchemaViolation(function () use ($field): void {
                WebhookFactory::fromArray(ApiFixtures::webhook('wh-null-required', [$field => null]));
            }, $field);
        }
    }

    /**
     * Builds a send-document response from a synthetic provider body.
     *
     * @param array<string, mixed> $response Decoded response fields returned by the fake transport.
     */
    private function sendDocument(array $response): DocumentSubmissionResult
    {
        $payload = new OutboundDocumentPayload(
            SubmittedDocumentId::fromString('INV-1'),
            PeppolDocumentTypeIdentifier::fromString('synthetic-document-type'),
            PeppolProcessIdentifier::fromString('synthetic-process'),
            Participant::parse('0245:2012345676'),
            Participant::parse('0245:2012345687'),
            Timestamp::fromIso8601('2026-11-15T09:30:00Z'),
            '<Invoice/>',
            Sha256Checksum::fromContent('<Invoice/>')
        );

        return (new SendDocumentEndpoint($this->connector($response, 202)))->send(
            new SendDocumentRequest(
                Participant::parse('0245:2012345676'),
                $payload,
                IdempotencyKey::fromString('00000000-0000-4000-8000-000000000001')
            )
        );
    }

    /** @param array<string, mixed> $response Decoded JSON body returned by the fake transport. */
    private function connector(array $response, int $status = 200): ApiConnector
    {
        return new ApiConnector(
            RecordingTransport::returning(ApiFixtures::jsonExchange($response, $status))
        );
    }

    /** @param callable(): mixed $parse Parser expected to reject the selected field. */
    private function assertSchemaViolation(callable $parse, string $field): void
    {
        try {
            $parse();
            $this->fail('Expected a schema violation for field ' . $field . '.');
        } catch (InvalidProviderResponseException $exception) {
            $this->assertSame($field, $exception->field());
        }
    }
}
