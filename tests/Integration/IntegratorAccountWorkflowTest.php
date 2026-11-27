<?php

namespace DreaM1\EfakturaConnector\Tests\Integration;

use DreaM1\EfakturaConnector\Core\Configuration\IntegratorAccountConfiguration;
use DreaM1\EfakturaConnector\Core\Configuration\IntegratorToken;
use DreaM1\EfakturaConnector\Core\Configuration\OAuthClientId;
use DreaM1\EfakturaConnector\Core\Documents\IdempotencyKey;
use DreaM1\EfakturaConnector\Core\Exceptions\ProviderConnectionException;
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
use DreaM1\EfakturaConnector\Core\IntegratorApi\ReplaceClientParticipantAccessRequest;
use DreaM1\EfakturaConnector\Core\Pagination\PageSize;
use DreaM1\EfakturaConnector\Core\Pagination\PageToken;
use DreaM1\EfakturaConnector\Core\Retry\RetryPolicy;
use DreaM1\EfakturaConnector\Domain\EFaktura\Participant;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Transport\Execution\HttpRequestExecutorInterface;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Transport\Execution\RetryingHttpRequestExecutor;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Transport\HttpExchangeRequest;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Transport\HttpExchangeResult;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Connection\IntegratorAccountClientFactory;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Http\Protocol\HttpHeaderName;
use DreaM1\EfakturaConnector\Tests\Support\OpenApiContract;
use LogicException;
use PHPUnit\Framework\TestCase;
use Throwable;

/** Simulates Integrator API workflows through the configured client factory and fake HTTP executor. */
final class IntegratorAccountWorkflowTest extends TestCase
{
    private const BASE_URL        = 'https://integrator.synthetic.test';
    private const INTEGRATOR_ID   = 'integrator-1';
    private const CLIENT_ID       = 'client-1';
    private const PARTICIPANT_ID  = '0245:2012345676';
    private const IDEMPOTENCY_KEY = '00000000-0000-4000-8000-000000000001';

    public function testSimulatesClientManagementAndParticipantOnboarding(): void
    {
        $fixtures = [
            $this->jsonFixture([
                'clients'       => [$this->clientResponse()],
                'nextPageToken' => 'client-next',
            ]),
            $this->jsonFixture([
                'client_id'     => self::CLIENT_ID,
                'client_secret' => 'synthetic-client-secret',
                'client_name'   => 'Synthetic integration',
                'status'        => 'APPROVED',
                'participants'  => [$this->accessResponse(true, false)],
            ]),
            $this->jsonFixture($this->clientResponse()),
            $this->jsonFixture($this->clientResponse([
                'participants' => [$this->accessResponse(true, true)],
            ])),
            $this->jsonFixture([
                'client_id'     => self::CLIENT_ID,
                'client_secret' => 'synthetic-rotated-secret',
            ]),
            $this->jsonFixture([
                'participants'  => [$this->participantResponse()],
                'nextPageToken' => 'participant-next',
            ]),
            $this->jsonFixture([
                'month'        => '202610',
                'participants' => [[
                    'participant_id' => self::PARTICIPANT_ID,
                    'sent'           => 12,
                    'received'       => 9,
                ]],
            ]),
            $this->jsonFixture([
                'activation_id'  => 'activation-1',
                'participant_id' => self::PARTICIPANT_ID,
                'integrator_id'  => self::INTEGRATOR_ID,
                'mode'           => 'SEND_AND_RECEIVE',
                'status'         => 'PENDING',
                'requested_at'   => '2026-11-24T12:00:00Z',
            ]),
            [
                'status'   => 204,
                'body'     => null,
                'exchange' => new HttpExchangeResult(204, '', [], microtime(true)),
            ],
        ];
        $executor = new class ($fixtures) implements HttpRequestExecutorInterface {
            /** @var array<int, array{status: int, body: array<string, mixed>|null, exchange: HttpExchangeResult}> */
            private array $fixtures;
            /** @var HttpExchangeRequest[] */
            public array $requests = [];

            public function __construct(array $fixtures)
            {
                $this->fixtures = $fixtures;
            }

            public function execute(HttpExchangeRequest $request): HttpExchangeResult
            {
                $this->requests[] = $request;

                if ($this->fixtures === []) {
                    throw new LogicException('No synthetic Integrator API response is queued.');
                }

                $fixture = array_shift($this->fixtures);

                return $fixture['exchange'];
            }
        };
        $client = (new IntegratorAccountClientFactory(static function () use ($executor) {
            return $executor;
        }))->create($this->configuration());

        $listed = $client->listClients(new ListClientsRequest(
            PageToken::fromString('client cursor'),
            PageSize::fromInt(20),
            IntegratorClientStatus::fromString(IntegratorClientStatus::APPROVED)
        ));
        $created = $client->createClient(new CreateClientRequest(
            IdempotencyKey::fromString(self::IDEMPOTENCY_KEY),
            'Synthetic integration',
            'integration@example.test',
            [new IntegratorParticipantAccess(Participant::parse(self::PARTICIPANT_ID), true, false)]
        ));
        $fetched = $client->getClient(new IntegratorClientReference(OAuthClientId::fromString(self::CLIENT_ID)));
        $updated = $client->replaceClientParticipantAccess(new ReplaceClientParticipantAccessRequest(
            new IntegratorClientReference(OAuthClientId::fromString(self::CLIENT_ID)),
            [new IntegratorParticipantAccess(Participant::parse(self::PARTICIPANT_ID), true, true)]
        ));
        $rotated      = $client->rotateClientSecret(new IntegratorClientReference(OAuthClientId::fromString(self::CLIENT_ID)));
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
        $client->deleteClient(new IntegratorClientReference(OAuthClientId::fromString(self::CLIENT_ID)));

        $this->assertSame('client-next', $listed->nextPageToken()->value());
        $this->assertSame(IntegratorClientStatus::APPROVED, $listed->clients()[0]->status()->value());
        $this->assertSame('synthetic-client-secret', $created->clientSecret()->reveal());
        $this->assertSame('Synthetic client', $fetched->name());
        $this->assertSame(self::PARTICIPANT_ID, $updated->participants()[0]->participantId()->identifier());
        $this->assertSame('synthetic-rotated-secret', $rotated->clientSecret()->reveal());
        $this->assertSame('participant-next', $participants->nextPageToken()->value());
        $this->assertSame('Synthetic Company', $participants->participants()[0]->legalName());
        $this->assertSame(12, $summary->participants()[0]->sent());
        $this->assertSame(9, $summary->participants()[0]->received());
        $this->assertSame('activation-1', $activation->activationId());
        $this->assertSame(ParticipantContractMode::SEND_AND_RECEIVE, $activation->mode()->value());
        $this->assertCount(9, $executor->requests);

        $expectedRequests = [
            ['GET', '/v1/integrators/integrator-1/clients?pageToken=client%20cursor&limit=20&status=APPROVED', true],
            ['POST', '/v1/integrators/integrator-1/clients', true],
            ['GET', '/v1/integrators/integrator-1/clients/client-1', true],
            ['PUT', '/v1/integrators/integrator-1/clients/client-1/participants', true],
            ['PUT', '/v1/integrators/integrator-1/clients/client-1/secret', false],
            ['GET', '/v1/integrators/integrator-1/participants?pageToken=participant%20cursor&limit=25', true],
            ['GET', '/v1/integrators/integrator-1/document-summary?month=202610', true],
            ['POST', '/v1/integrators/integrator-1/participants/0245%3A2012345676/activations', true],
            ['DELETE', '/v1/integrators/integrator-1/clients/client-1', false],
        ];
        $contract = new OpenApiContract();

        foreach ($executor->requests as $index => $request) {
            [$method, $path, $retrySafe] = $expectedRequests[$index];
            $this->assertSame($method, $request->httpMethod()->value());
            $this->assertSame($path, $request->path());
            $this->assertSame($retrySafe, $request->isRetrySafe());
            $this->assertSame(self::BASE_URL, $request->baseUrlOrNull()->value());

            $headers = [];

            foreach ($request->headers()->all() as $header) {
                $headers[$header->name()] = $header->value();
            }

            $this->assertSame('synthetic-integrator-token', $headers['x-integrator-token']);
            $this->assertSame(
                $index === 1 || $index === 7 ? self::IDEMPOTENCY_KEY : null,
                $headers['idempotency-key'] ?? null
            );

            $contractRequest = $request->withoutHeader(HttpHeaderName::integratorToken());
            $requestErrors   = $contract->validateRequest($contractRequest);
            $this->assertSame([], $requestErrors, implode('; ', $requestErrors));

            $responseErrors = $contract->validateResponse(
                $request->path(),
                strtolower($request->httpMethod()->value()),
                $fixtures[$index]['status'],
                $fixtures[$index]['body']
            );
            $this->assertSame([], $responseErrors, implode('; ', $responseErrors));
        }

        $this->assertSame([
            'participants'  => [$this->accessResponse(true, false)],
            'name'          => 'Synthetic integration',
            'contact_email' => 'integration@example.test',
        ], json_decode($executor->requests[1]->body(), true));
        $this->assertSame([
            'participants' => [$this->accessResponse(true, true)],
        ], json_decode($executor->requests[3]->body(), true));
        $this->assertSame([
            'mode'                 => 'SEND_AND_RECEIVE',
            'requested_by_user_id' => 'user-1',
        ], json_decode($executor->requests[7]->body(), true));
    }

    public function testSimulatesRetryForSafeLookupButNotSecretRotation(): void
    {
        $executor = new class ([
            new ProviderConnectionException('Synthetic lookup connection drop.'),
            $this->jsonFixture(['clients' => []])['exchange'],
            new ProviderConnectionException('Synthetic secret rotation connection drop.'),
        ]) implements HttpRequestExecutorInterface {
            /** @var array<int, HttpExchangeResult|Throwable> */
            private array $responses;
            /** @var HttpExchangeRequest[] */
            public array $requests = [];

            public function __construct(array $responses)
            {
                $this->responses = $responses;
            }

            public function execute(HttpExchangeRequest $request): HttpExchangeResult
            {
                $this->requests[] = $request;
                $response         = array_shift($this->responses);

                if ($response instanceof Throwable) {
                    throw $response;
                }

                return $response;
            }
        };
        $delays           = [];
        $retryingExecutor = new RetryingHttpRequestExecutor(
            $executor,
            new RetryPolicy(2, 0, 0),
            static function (int $delayMs) use (&$delays): void {
                $delays[] = $delayMs;
            }
        );
        $client = (new IntegratorAccountClientFactory(static function () use ($retryingExecutor) {
            return $retryingExecutor;
        }))->create($this->configuration());

        $this->assertCount(0, $client->listClients(new ListClientsRequest())->clients());

        try {
            $client->rotateClientSecret(new IntegratorClientReference(OAuthClientId::fromString(self::CLIENT_ID)));
            $this->fail('Secret rotation must surface its ambiguous outcome without replaying the request.');
        } catch (ProviderConnectionException $exception) {
            $this->assertSame('Synthetic secret rotation connection drop.', $exception->getMessage());
        }

        $this->assertCount(3, $executor->requests);
        $this->assertSame(['GET', 'GET', 'PUT'], array_map(
            static function (HttpExchangeRequest $request): string {
                return $request->httpMethod()->value();
            },
            $executor->requests
        ));
        $this->assertSame([true, true, false], array_map(
            static function (HttpExchangeRequest $request): bool {
                return $request->isRetrySafe();
            },
            $executor->requests
        ));
        $this->assertSame([0], $delays);
    }

    private function configuration(): IntegratorAccountConfiguration
    {
        return new IntegratorAccountConfiguration(
            self::BASE_URL,
            IntegratorId::fromString(self::INTEGRATOR_ID),
            IntegratorToken::fromString('synthetic-integrator-token')
        );
    }

    /** @param array<string, mixed> $body @return array{status: int, body: array<string, mixed>, exchange: HttpExchangeResult} */
    private function jsonFixture(array $body, int $status = 200): array
    {
        return [
            'status'   => $status,
            'body'     => $body,
            'exchange' => new HttpExchangeResult(
                $status,
                (string) json_encode($body),
                ['content-type' => 'application/json'],
                microtime(true)
            ),
        ];
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
    private function accessResponse(bool $canSend, bool $canReceive): array
    {
        return [
            'participant_id' => self::PARTICIPANT_ID,
            'can_send'       => $canSend,
            'can_receive'    => $canReceive,
        ];
    }

    /** @return array<string, mixed> */
    private function participantResponse(): array
    {
        return [
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
        ];
    }
}
