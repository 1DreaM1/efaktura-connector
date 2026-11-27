<?php

namespace DreaM1\EfakturaConnector\Tests\Unit\Infrastructure\EFaktura\Api;

use DreaM1\EfakturaConnector\Core\Configuration\IntegratorAccountConfiguration;
use DreaM1\EfakturaConnector\Core\Configuration\IntegratorToken;
use DreaM1\EfakturaConnector\Core\Configuration\OAuthClientId;
use DreaM1\EfakturaConnector\Core\Contracts\IntegratorAccountClientInterface;
use DreaM1\EfakturaConnector\Core\Documents\IdempotencyKey;
use DreaM1\EfakturaConnector\Core\Exceptions\ConfigurationException;
use DreaM1\EfakturaConnector\Core\Exceptions\InvalidProviderResponseException;
use DreaM1\EfakturaConnector\Core\IntegratorApi\ActivateParticipantRequest;
use DreaM1\EfakturaConnector\Core\IntegratorApi\CreateClientRequest;
use DreaM1\EfakturaConnector\Core\IntegratorApi\GetDocumentSummaryRequest;
use DreaM1\EfakturaConnector\Core\IntegratorApi\IntegratorClientReference;
use DreaM1\EfakturaConnector\Core\IntegratorApi\IntegratorClientStatus;
use DreaM1\EfakturaConnector\Core\IntegratorApi\IntegratorId;
use DreaM1\EfakturaConnector\Core\IntegratorApi\IntegratorParticipantAccess;
use DreaM1\EfakturaConnector\Core\IntegratorApi\ListClientsRequest;
use DreaM1\EfakturaConnector\Core\IntegratorApi\ListParticipantsRequest;
use DreaM1\EfakturaConnector\Core\IntegratorApi\ParticipantContractMode;
use DreaM1\EfakturaConnector\Core\IntegratorApi\PdsSigningSecret;
use DreaM1\EfakturaConnector\Core\IntegratorApi\PdsSubject;
use DreaM1\EfakturaConnector\Core\IntegratorApi\PdsSubjectBatch;
use DreaM1\EfakturaConnector\Core\IntegratorApi\PdsVerificationToken;
use DreaM1\EfakturaConnector\Core\IntegratorApi\ReplaceClientParticipantAccessRequest;
use DreaM1\EfakturaConnector\Core\IntegratorApi\SubmitPdsSubjectsRequest;
use DreaM1\EfakturaConnector\Core\Pagination\PageSize;
use DreaM1\EfakturaConnector\Core\Pagination\PageToken;
use DreaM1\EfakturaConnector\Domain\EFaktura\Participant;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\ApiConnector;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Authentication\IntegratorTokenRequestSender;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Authentication\PdsAuthenticatedRequestSender;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Transport\Coordination\HttpRequestBackpressureKey;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Transport\Execution\HttpRequestExecutorInterface;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Transport\HttpExchangeRequest;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Transport\HttpExchangeResult;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Transport\ProviderTransport;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Connection\IntegratorAccountClient;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Connection\IntegratorAccountClientFactory;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Http\Protocol\HttpHeaderLine;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Http\Protocol\HttpHeaderName;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Http\Protocol\HttpMethod;
use DreaM1\EfakturaConnector\Support\HashAlgorithm;
use DreaM1\EfakturaConnector\Support\Timestamp;
use DreaM1\EfakturaConnector\Tests\Support\HttpExchangeRequestFactory;
use DreaM1\EfakturaConnector\Tests\Support\OpenApiContract;
use DreaM1\EfakturaConnector\Tests\Support\RecordingTransport;
use PHPUnit\Framework\TestCase;
use stdClass;

/** Tests Integrator API request mapping, response mapping, authentication and retry rules. */
final class IntegratorAccountClientTest extends TestCase
{
    private const BASE_URL        = 'https://integrator.synthetic.test';
    private const INTEGRATOR_ID   = 'integrator-1';
    private const CLIENT_ID       = 'client-1';
    private const PARTICIPANT_ID  = '0245:2012345676';
    private const IDEMPOTENCY_KEY = '00000000-0000-4000-8000-000000000001';

    public function testClientOperationsMatchOpenApiAndPreserveRetrySemantics(): void
    {
        $transport = new RecordingTransport(function (HttpExchangeRequest $request): HttpExchangeResult {
            if ($request->httpMethod()->value() === 'DELETE') {
                return new HttpExchangeResult(204, '', [], microtime(true));
            }

            if ($request->httpMethod()->value() === 'POST') {
                return $this->jsonExchange([
                    'client_id'     => self::CLIENT_ID,
                    'client_secret' => 'synthetic-client-secret',
                    'client_name'   => 'Synthetic client',
                    'status'        => 'APPROVED',
                ]);
            }

            if (parse_url($request->path(), PHP_URL_QUERY) !== null) {
                return $this->jsonExchange([
                    'clients'       => [$this->clientResponse()],
                    'nextPageToken' => 'next-page',
                ]);
            }

            return $this->jsonExchange($this->clientResponse());
        });
        $client = $this->client($transport);

        $listed = $client->listClients(new ListClientsRequest(
            PageToken::fromString('next page'),
            PageSize::fromInt(10),
            IntegratorClientStatus::fromString(IntegratorClientStatus::APPROVED)
        ));
        $created = $client->createClient(new CreateClientRequest(
            IdempotencyKey::fromString(self::IDEMPOTENCY_KEY),
            'Synthetic client',
            'contact@example.test',
            [new IntegratorParticipantAccess(Participant::parse(self::PARTICIPANT_ID), true, false)]
        ));
        $fetched = $client->getClient(new IntegratorClientReference(OAuthClientId::fromString(self::CLIENT_ID)));
        $client->deleteClient(new IntegratorClientReference(OAuthClientId::fromString(self::CLIENT_ID)));

        $this->assertSame('next-page', $listed->nextPageToken()->value());
        $this->assertSame(IntegratorClientStatus::APPROVED, $listed->clients()[0]->status()->value());
        $this->assertSame('synthetic-client-secret', $created->clientSecret()->reveal());
        $this->assertSame(self::CLIENT_ID, $created->clientId()->value());
        $this->assertSame('Synthetic client', $fetched->name());
        $this->assertSame(self::CLIENT_ID, $fetched->clientId()->value());

        [$list, $create, $get, $delete] = $transport->requests();
        $this->assertRequest($list, 'GET', '/v1/integrators/integrator-1/clients?pageToken=next%20page&limit=10&status=APPROVED', [], true);
        $this->assertRequest(
            $create,
            'POST',
            '/v1/integrators/integrator-1/clients',
            ['Idempotency-Key: ' . self::IDEMPOTENCY_KEY],
            true
        );
        $this->assertSame([
            'participants' => [[
                'participant_id' => self::PARTICIPANT_ID,
                'can_send'       => true,
                'can_receive'    => false,
            ]],
            'name'          => 'Synthetic client',
            'contact_email' => 'contact@example.test',
        ], json_decode($create->body(), true));
        $this->assertRequest($get, 'GET', '/v1/integrators/integrator-1/clients/client-1', [], true);
        $this->assertRequest($delete, 'DELETE', '/v1/integrators/integrator-1/clients/client-1', [], false);

        $output = '';
        ob_start();
        var_dump($created);
        $output = (string) ob_get_clean();
        $this->assertStringNotContainsString('synthetic-client-secret', $output);
        $this->assertStringContainsString('[redacted]', $output);
    }

    public function testParticipantAccessAndOnboardingOperationsMatchOpenApi(): void
    {
        $transport = new RecordingTransport(function (HttpExchangeRequest $request): HttpExchangeResult {
            $path = parse_url($request->path(), PHP_URL_PATH);

            if (strpos($path, '/secret') !== false) {
                return $this->jsonExchange([
                    'client_id'     => self::CLIENT_ID,
                    'client_secret' => 'synthetic-rotated-secret',
                ]);
            }

            if ($path === '/v1/integrators/integrator-1/participants') {
                return $this->jsonExchange([
                    'participants'  => [$this->participantResponse()],
                    'nextPageToken' => 'participant-next',
                ]);
            }

            if (strpos($path, '/activations') !== false) {
                return $this->jsonExchange([
                    'activation_id'  => 'activation-1',
                    'participant_id' => self::PARTICIPANT_ID,
                    'integrator_id'  => self::INTEGRATOR_ID,
                    'mode'           => 'SEND_AND_RECEIVE',
                    'status'         => 'PENDING',
                    'requested_at'   => '2026-11-24T12:00:00Z',
                ]);
            }

            if (strpos($path, '/document-summary') !== false) {
                return $this->jsonExchange([
                    'month'        => '202610',
                    'participants' => [[
                        'participant_id' => self::PARTICIPANT_ID,
                        'sent'           => 12,
                        'received'       => 9,
                    ]],
                ]);
            }

            return $this->jsonExchange($this->clientResponse([
                'participants' => [[
                    'participant_id' => self::PARTICIPANT_ID,
                    'can_send'       => true,
                    'can_receive'    => true,
                ]],
            ]));
        });
        $client = $this->client($transport);

        $access = $client->replaceClientParticipantAccess(
            new ReplaceClientParticipantAccessRequest(
                new IntegratorClientReference(OAuthClientId::fromString(self::CLIENT_ID)),
                [new IntegratorParticipantAccess(Participant::parse(self::PARTICIPANT_ID), true, true)]
            )
        );
        $rotation     = $client->rotateClientSecret(new IntegratorClientReference(OAuthClientId::fromString(self::CLIENT_ID)));
        $participants = $client->listParticipants(new ListParticipantsRequest(
            PageToken::fromString('participant cursor'),
            PageSize::fromInt(25)
        ));
        $summary    = $client->getDocumentSummary(new GetDocumentSummaryRequest('202610'));
        $activation = $client->requestParticipantActivation(new ActivateParticipantRequest(
            Participant::parse(self::PARTICIPANT_ID),
            ParticipantContractMode::fromString(ParticipantContractMode::SEND_AND_RECEIVE),
            IdempotencyKey::fromString(self::IDEMPOTENCY_KEY),
            'user-1'
        ));

        $this->assertSame(self::PARTICIPANT_ID, $access->participants()[0]->participantId()->identifier());
        $this->assertSame('synthetic-rotated-secret', $rotation->clientSecret()->reveal());
        $this->assertSame('participant-next', $participants->nextPageToken()->value());
        $this->assertSame(ParticipantContractMode::SEND_AND_RECEIVE, $participants->participants()[0]->activeContractMode()->value());
        $this->assertSame(12, $summary->participants()[0]->sent());
        $this->assertSame(self::PARTICIPANT_ID, $summary->participants()[0]->participantId()->identifier());
        $this->assertSame(self::PARTICIPANT_ID, $activation->participantId()->identifier());
        $this->assertSame(self::INTEGRATOR_ID, $activation->integratorId()->value());
        $this->assertSame(ParticipantContractMode::SEND_AND_RECEIVE, $activation->mode()->value());

        ob_start();
        var_dump($rotation);
        $output = (string) ob_get_clean();
        $this->assertStringNotContainsString('synthetic-rotated-secret', $output);
        $this->assertStringContainsString('[redacted]', $output);

        [$replace, $rotate, $list, $summaryRequest, $activate] = $transport->requests();
        $this->assertRequest($replace, 'PUT', '/v1/integrators/integrator-1/clients/client-1/participants', [], true);
        $this->assertSame([
            'participants' => [[
                'participant_id' => self::PARTICIPANT_ID,
                'can_send'       => true,
                'can_receive'    => true,
            ]],
        ], json_decode($replace->body(), true));
        $this->assertRequest($rotate, 'PUT', '/v1/integrators/integrator-1/clients/client-1/secret', [], false);
        $this->assertRequest(
            $list,
            'GET',
            '/v1/integrators/integrator-1/participants?pageToken=participant%20cursor&limit=25',
            [],
            true
        );
        $this->assertRequest($summaryRequest, 'GET', '/v1/integrators/integrator-1/document-summary?month=202610', [], true);
        $this->assertRequest(
            $activate,
            'POST',
            '/v1/integrators/integrator-1/participants/0245%3A2012345676/activations',
            ['Idempotency-Key: ' . self::IDEMPOTENCY_KEY],
            true
        );
        $this->assertSame([
            'mode'                 => 'SEND_AND_RECEIVE',
            'requested_by_user_id' => 'user-1',
        ], json_decode($activate->body(), true));
    }

    public function testFactoryAddsOnlyIntegratorTokenAndBindsProviderUrl(): void
    {
        $executor = new class ($this->jsonExchange(['clients' => []])) implements HttpRequestExecutorInterface {
            private HttpExchangeResult $exchange;
            /** @var HttpExchangeRequest[] */
            public array $requests = [];

            public function __construct(HttpExchangeResult $exchange)
            {
                $this->exchange = $exchange;
            }

            public function execute(HttpExchangeRequest $request): HttpExchangeResult
            {
                $this->requests[] = $request;

                return $this->exchange;
            }
        };
        $configuration = new IntegratorAccountConfiguration(
            self::BASE_URL,
            IntegratorId::fromString(self::INTEGRATOR_ID),
            IntegratorToken::fromString('synthetic-integrator-token')
        );

        ob_start();
        var_dump($configuration);
        $configurationOutput = (string) ob_get_clean();
        $this->assertStringNotContainsString('synthetic-integrator-token', $configurationOutput);
        $this->assertStringContainsString('[redacted]', $configurationOutput);

        $client = (new IntegratorAccountClientFactory(static function () use ($executor) {
            return $executor;
        }))->create($configuration);

        $this->assertInstanceOf(IntegratorAccountClientInterface::class, $client);
        $client->listClients(new ListClientsRequest());

        $pdsSecret = PdsSigningSecret::fromString('synthetic-pds-signing-secret');
        $client->submitPdsSubjects(new SubmitPdsSubjectsRequest(new PdsSubjectBatch([]), $pdsSecret));

        $request = $executor->requests[0];
        $headers = array_map(static function (HttpHeaderLine $line): string {
            return $line->toString();
        }, $request->headers()->all());
        $this->assertSame(['X-Integrator-Token: synthetic-integrator-token'], $headers);
        $this->assertSame(self::BASE_URL, $request->baseUrlOrNull()->value());
        $this->assertSame(
            HttpRequestBackpressureKey::scopeForConfiguredProvider(
                self::BASE_URL,
                self::INTEGRATOR_ID
            ),
            $request->backpressureScopeKey()
        );
        $this->assertNotSame('synthetic-integrator-token', $request->backpressureScopeKey());
        $this->assertNotNull($request->backpressureScopeKey());

        $pdsRequest = $executor->requests[1];
        $pdsHeaders = array_map(static function (HttpHeaderLine $line): string {
            return $line->toString();
        }, $pdsRequest->headers()->all());
        $this->assertSame([
            'X-PDS-Secret: ' . hash_hmac(HashAlgorithm::SHA_512, '[]', $pdsSecret->reveal()),
        ], $pdsHeaders);
        $this->assertSame(self::BASE_URL, $pdsRequest->baseUrlOrNull()->value());
        $this->assertSame($request->backpressureScopeKey(), $pdsRequest->backpressureScopeKey());

        $untrusted = HttpExchangeRequestFactory::create(HttpMethod::get(), '/integrator-api-test', [
            HttpHeaderLine::fromParts(HttpHeaderName::integratorToken(), 'caller-supplied-token'),
            HttpHeaderLine::fromParts(HttpHeaderName::authorization(), 'Bearer caller-supplied-token'),
            HttpHeaderLine::fromParts(HttpHeaderName::pdsSecret(), 'caller-supplied-signature'),
        ]);
        $transport = ProviderTransport::forIntegratorAccount($configuration, $executor);
        (new IntegratorTokenRequestSender($transport, $configuration))->send($untrusted);
        $authenticatedHeaders = array_map(static function (HttpHeaderLine $line): string {
            return $line->toString();
        }, $executor->requests[2]->headers()->all());
        $this->assertSame(['X-Integrator-Token: synthetic-integrator-token'], $authenticatedHeaders);
    }

    public function testFactoryPassesTheAccountConfigurationToTheExecutorFactory(): void
    {
        $configuration = $this->accountConfiguration();
        $received      = null;
        $executor      = $this->createMock(HttpRequestExecutorInterface::class);

        $client = (new IntegratorAccountClientFactory(
            static function ($given) use ($executor, &$received) {
                $received = $given;

                return $executor;
            }
        ))->create($configuration);

        $this->assertInstanceOf(IntegratorAccountClientInterface::class, $client);
        $this->assertSame($configuration, $received);
    }

    public function testFactoryRejectsAnExecutorFactoryThatReturnsSomethingElse(): void
    {
        $factory = new IntegratorAccountClientFactory(static function () {
            return new stdClass();
        });

        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage('The executor factory returned an invalid executor.');

        $factory->create($this->accountConfiguration());
    }

    public function testPdsSubjectSubmissionUsesTypedPayloadAndSignsExactJsonBody(): void
    {
        $transport = RecordingTransport::returning($this->jsonExchange([
            'code'        => 200,
            'description' => 'Accepted',
        ]));
        $secret = PdsSigningSecret::fromString('synthetic-pds-signing-secret');
        $batch  = new PdsSubjectBatch([
            new PdsSubject(
                Timestamp::fromIso8601('2026-11-24T12:00:00Z'),
                '2022706466',
                'Synthetic Company',
                'info@example.test',
                '+421900000001',
                PdsVerificationToken::fromString('synthetic-verification-token')
            ),
        ]);

        $response = $this->client($transport)->submitPdsSubjects(new SubmitPdsSubjectsRequest($batch, $secret));

        $this->assertSame(200, $response->code());
        $this->assertSame('Accepted', $response->description());

        [$request]    = $transport->requests();
        $expectedBody = '[{"created":"2026-11-24T12:00:00Z","dic":"2022706466",'
            . '"legalName":"Synthetic Company","company_email":"info@example.test",'
            . '"company_phone":"+421900000001","verification_token":"synthetic-verification-token"}]';
        $expectedSignature = hash_hmac(HashAlgorithm::SHA_512, $expectedBody, $secret->reveal());

        $this->assertSame($expectedBody, $request->body());
        $this->assertRequest(
            $request,
            'POST',
            '/v1/integrators/integrator-1/pds/subjects',
            ['X-PDS-Secret: ' . $expectedSignature],
            false
        );
    }

    public function testPdsSubjectSubmissionOmitsOptionalFieldsAndAcceptsEmptyAcknowledgement(): void
    {
        $transport = RecordingTransport::returning(new HttpExchangeResult(
            200,
            '{}',
            ['content-type' => 'application/json'],
            microtime(true)
        ));
        $secret = PdsSigningSecret::fromString('synthetic-pds-signing-secret');
        $batch  = new PdsSubjectBatch([
            new PdsSubject(null, null, null, null, null, null),
        ]);

        $response = $this->client($transport)->submitPdsSubjects(new SubmitPdsSubjectsRequest($batch, $secret));

        $this->assertNull($response->code());
        $this->assertNull($response->description());

        [$request] = $transport->requests();
        $this->assertSame('[{}]', $request->body());
        $this->assertRequest(
            $request,
            'POST',
            '/v1/integrators/integrator-1/pds/subjects',
            ['X-PDS-Secret: ' . hash_hmac(HashAlgorithm::SHA_512, '[{}]', $secret->reveal())],
            false
        );
    }

    public function testPdsSubjectSubmissionRejectsInvalidAcknowledgementFieldTypes(): void
    {
        $transport = RecordingTransport::returning($this->jsonExchange(['code' => 'accepted']));

        $this->expectException(InvalidProviderResponseException::class);

        $this->client($transport)->submitPdsSubjects(new SubmitPdsSubjectsRequest(
            new PdsSubjectBatch([]),
            PdsSigningSecret::fromString('synthetic-pds-signing-secret')
        ));
    }

    public function testUnknownClientStatusIsPreservedForHostClassification(): void
    {
        $transport = RecordingTransport::returning($this->jsonExchange(['status' => 'SUSPENDED']));

        $client = $this->client($transport)->getClient(
            new IntegratorClientReference(OAuthClientId::fromString(self::CLIENT_ID))
        );

        $this->assertSame('SUSPENDED', $client->status()->value());
        $this->assertFalse($client->status()->isKnown());
    }

    public function testUnknownParticipantContractModeIsPreservedForHostClassification(): void
    {
        $transport = RecordingTransport::returning($this->jsonExchange([
            'participants' => [$this->participantResponse(['active_contract_mode' => 'SEND_ARCHIVE'])],
        ]));

        $page = $this->client($transport)->listParticipants(new ListParticipantsRequest(null, PageSize::fromInt(10)));
        $mode = $page->participants()[0]->activeContractMode();

        $this->assertSame('SEND_ARCHIVE', $mode->value());
        $this->assertFalse($mode->isKnown());
    }

    public function testIntegratorAndParticipantIdentifiersUseTheirDomainTypes(): void
    {
        $integratorId  = IntegratorId::fromString(' account/id ');
        $participantId = Participant::parse('0245:1234567890');

        $this->assertSame('account/id', $integratorId->value());
        $this->assertSame('0245:1234567890', $participantId->identifier());
    }

    /** @param string[] $headers */
    private function assertRequest(
        HttpExchangeRequest $request,
        string $method,
        string $path,
        array $headers,
        bool $retrySafe
    ): void {
        $this->assertSame($method, $request->httpMethod()->value());
        $this->assertSame($path, $request->path());
        $this->assertSame($retrySafe, $request->isRetrySafe());
        $this->assertSame($headers, array_map(static function (HttpHeaderLine $line): string {
            return $line->toString();
        }, $request->headers()->all()));

        $errors = (new OpenApiContract())->validateRequest($request);
        $this->assertSame([], $errors, implode('; ', $errors));
    }

    private function accountConfiguration(): IntegratorAccountConfiguration
    {
        return new IntegratorAccountConfiguration(
            self::BASE_URL,
            IntegratorId::fromString(self::INTEGRATOR_ID),
            IntegratorToken::fromString('synthetic-integrator-token')
        );
    }

    private function client(RecordingTransport $transport): IntegratorAccountClient
    {
        $configuration = new IntegratorAccountConfiguration(
            self::BASE_URL,
            IntegratorId::fromString(self::INTEGRATOR_ID),
            IntegratorToken::fromString('synthetic-integrator-token')
        );
        $api    = new ApiConnector($transport);
        $pdsApi = new ApiConnector(new PdsAuthenticatedRequestSender($transport));

        return new IntegratorAccountClient($configuration, $api, $pdsApi);
    }

    /** @param array<string, mixed> $data */
    private function jsonExchange(array $data, int $status = 200): HttpExchangeResult
    {
        return new HttpExchangeResult($status, json_encode($data), ['content-type' => 'application/json'], microtime(true));
    }

    /** @param array<string, mixed> $overrides @return array<string, mixed> */
    private function clientResponse(array $overrides = []): array
    {
        return array_merge([
            'client_id'   => self::CLIENT_ID,
            'client_name' => 'Synthetic client',
            'status'      => 'APPROVED',
            'created_at'  => '2026-11-20T10:00:00Z',
            'approved_at' => '2026-11-21T10:00:00Z',
        ], $overrides);
    }

    /** @return array<string, mixed> */
    private function participantResponse(array $overrides = []): array
    {
        return array_merge([
            'participant_id'             => self::PARTICIPANT_ID,
            'dic'                        => '2022706466',
            'legal_name'                 => 'Synthetic Company',
            'company_email'              => 'contact@example.test',
            'company_phone'              => '+421900000001',
            'source_created_at'          => '2026-11-20T10:00:00Z',
            'received_at'                => '2026-11-21T10:00:00Z',
            'internal_transfer_required' => false,
            'is_owner'                   => true,
            'active_contract_mode'       => 'SEND_AND_RECEIVE',
        ], $overrides);
    }
}
