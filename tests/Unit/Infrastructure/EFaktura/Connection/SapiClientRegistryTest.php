<?php

namespace DreaM1\EfakturaConnector\Tests\Unit\Infrastructure\EFaktura\Connection;

use DreaM1\EfakturaConnector\Core\Configuration\SapiClientsConfiguration;
use DreaM1\EfakturaConnector\Core\Contracts\SapiClientFactoryInterface;
use DreaM1\EfakturaConnector\Core\Contracts\SapiClientInterface;
use DreaM1\EfakturaConnector\Core\Exceptions\ConfigurationException;
use DreaM1\EfakturaConnector\Core\Ubl\Contracts\UblDocumentWriterInterface;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Authentication\AuthenticatedRequestSender;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Authentication\SapiTokenCacheInterface;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Authentication\SapiTokenProvider;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Transport\Execution\HttpRequestExecutorInterface;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Transport\HttpExchangeRequest;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Connection\SapiClientFactory;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Connection\SapiClientRegistry;
use DreaM1\EfakturaConnector\Tests\Support\ApiFixtures;
use DreaM1\EfakturaConnector\Tests\Support\ReflectionAccess;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use stdClass;

/** Tests selection and credential isolation for named SAPI clients. */
class SapiClientRegistryTest extends TestCase
{
    public function testClientCreationIsDelegatedLazilyAndCachedByTheRegistry(): void
    {
        $configuration = SapiClientsConfiguration::fromArray([
            'default_client' => 'tenant-a',
            'clients'        => [
                'tenant-a' => [
                    'base_url'      => 'https://provider.synthetic.test',
                    'client_id'     => 'client-a',
                    'client_secret' => 'secret-a',
                    'client_scope'  => 'document:send',
                ],
            ],
        ]);
        $client  = $this->createMock(SapiClientInterface::class);
        $factory = $this->createMock(SapiClientFactoryInterface::class);
        $factory->expects($this->once())
            ->method('create')
            ->with('tenant-a', $configuration->client('tenant-a'))
            ->willReturn($client);

        $registry = new SapiClientRegistry($configuration, $factory);

        $this->assertSame($client, $registry->client('tenant-a'));
        $this->assertSame($client, $registry->client('tenant-a'));
        $this->assertSame($client, $registry->defaultClient());
    }

    public function testFactoryPassesEachClientProfileToTheExecutorFactory(): void
    {
        $configuration = SapiClientsConfiguration::fromArray([
            'clients' => [
                'default' => ['client_id' => 'client-a', 'client_secret' => 'secret-a'],
            ],
        ]);
        $profile  = $configuration->client('default');
        $received = null;
        $executor = $this->createMock(HttpRequestExecutorInterface::class);

        $client = (new SapiClientFactory(
            static function ($given) use ($executor, &$received) {
                $received = $given;

                return $executor;
            },
            $this->createMock(SapiTokenCacheInterface::class),
            $this->createMock(UblDocumentWriterInterface::class)
        ))->create('default', $profile);

        $this->assertSame('default', $client->name());
        $this->assertSame($profile, $received);
    }

    public function testFactoryRejectsAnExecutorFactoryThatReturnsSomethingElse(): void
    {
        $configuration = SapiClientsConfiguration::fromArray([
            'clients' => [
                'default' => ['client_id' => 'client-a', 'client_secret' => 'secret-a'],
            ],
        ]);
        $factory = new SapiClientFactory(
            static function () {
                return new stdClass();
            },
            $this->createMock(SapiTokenCacheInterface::class),
            $this->createMock(UblDocumentWriterInterface::class)
        );

        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage('The executor factory returned an invalid executor.');

        $factory->create('default', $configuration->client('default'));
    }

    public function testClientFacadesAreCreatedLazilyAndReusedByTypedService(): void
    {
        $configuration = SapiClientsConfiguration::fromArray([
            'default_client' => 'tenant-a',
            'clients'        => [
                'tenant-a' => [
                    'base_url'      => 'https://provider.synthetic.test',
                    'client_id'     => 'client-a',
                    'client_secret' => 'secret-a',
                    'client_scope'  => 'document:send',
                ],
            ],
        ]);
        $executor = $this->createMock(HttpRequestExecutorInterface::class);
        $executor->expects($this->never())->method('execute');

        $client = (new SapiClientFactory(
            static function () use ($executor) {
                return $executor;
            },
            $this->createMock(SapiTokenCacheInterface::class),
            $this->createMock(UblDocumentWriterInterface::class)
        ))->create('tenant-a', $configuration->client('tenant-a'));
        $facades = $client;

        $this->assertNull($this->privateProperty($facades, 'documentTrackingProvider'));
        $this->assertNull($this->privateProperty($facades, 'webhookRegistry'));

        $trackingProvider = $client->documentTrackingProvider();
        $this->assertSame($trackingProvider, $client->documentTrackingProvider());
        $this->assertNotNull($this->privateProperty($facades, 'documentTrackingProvider'));
        $this->assertNull($this->privateProperty($facades, 'webhookRegistry'));

        $services = [
            'documentSubmissionGateway',
            'peppolGatewayDocumentSubmission',
            'ublDraftSubmission',
            'receivedDocumentProvider',
            'webhookRegistry',
            'participantProfileProvider',
            'tokenHealthProvider',
            'documentDiscoveryProvider',
            'providerDocumentValidationCheck',
            'recipientCapabilityCheck',
        ];

        foreach ($services as $service) {
            $instance = $client->{$service}();
            $this->assertSame($instance, $client->{$service}());
        }
    }

    public function testClientsUseTheirOwnCredentialsAndDistinctTokenCacheKeys(): void
    {
        $requestClientIds = [];
        $tokenCacheKeys   = [];
        $executor         = $this->createMock(HttpRequestExecutorInterface::class);
        $executor->expects($this->exactly(2))
            ->method('execute')
            ->willReturnCallback(function (HttpExchangeRequest $request) use (&$requestClientIds) {
                $this->assertSame('/sapi/auth/token', $request->path());
                $payload = json_decode($request->body(), true);
                $this->assertIsArray($payload);
                $requestClientIds[] = $payload['client_id'];

                return ApiFixtures::jsonExchange(ApiFixtures::token([
                    'access_token' => 'token-for-' . $payload['client_id'],
                ]));
            });

        $cache = $this->createMock(SapiTokenCacheInterface::class);
        $cache->method('load')->willReturn(null);
        $cache->expects($this->exactly(2))
            ->method('store')
            ->willReturnCallback(function (string $key) use (&$tokenCacheKeys): void {
                $tokenCacheKeys[] = $key;
            });

        $configuration = SapiClientsConfiguration::fromArray([
            'default_client' => 'tenant-a',
            'clients'        => [
                'tenant-a' => [
                    'base_url'      => 'https://provider.synthetic.test',
                    'client_id'     => 'client-a',
                    'client_secret' => 'secret-a',
                    'client_scope'  => 'document:send',
                ],
                'tenant-b' => [
                    'base_url'      => 'https://provider.synthetic.test',
                    'client_id'     => 'client-b',
                    'client_secret' => 'secret-b',
                    'client_scope'  => 'document:receive',
                ],
            ],
        ]);
        $registry = new SapiClientRegistry(
            $configuration,
            new SapiClientFactory(
                static function () use ($executor) {
                    return $executor;
                },
                $cache,
                $this->createMock(UblDocumentWriterInterface::class)
            )
        );

        $clientA = $registry->client('tenant-a');
        $clientB = $registry->client('tenant-b');
        $tokenA  = $this->tokenProvider($clientA)->getToken();
        $tokenB  = $this->tokenProvider($clientB)->getToken();

        $this->assertSame('tenant-a', $registry->defaultClient()->name());
        $this->assertSame(['client-a', 'client-b'], $requestClientIds);
        $this->assertSame('token-for-client-a', $tokenA->accessToken()->reveal());
        $this->assertSame('token-for-client-b', $tokenB->accessToken()->reveal());
        $this->assertCount(2, array_unique($tokenCacheKeys));
        $this->assertStringNotContainsString('secret-a', $tokenCacheKeys[0]);
        $this->assertStringNotContainsString('secret-b', $tokenCacheKeys[1]);
    }

    private function tokenProvider(object $client): SapiTokenProvider
    {
        $facades = $client;
        $api     = $this->privateProperty($facades, 'api');
        $sender  = $this->privateProperty($api, 'transport');

        $this->assertInstanceOf(AuthenticatedRequestSender::class, $sender);

        $provider = $this->privateProperty($sender, 'tokenProvider');
        $this->assertInstanceOf(SapiTokenProvider::class, $provider);

        return $provider;
    }

    /** Reads an object property for pipeline isolation assertions. */
    private function privateProperty(object $object, string $name)
    {
        $property = (new ReflectionClass($object))->getProperty($name);
        ReflectionAccess::makeAccessible($property);

        return $property->getValue($object);
    }
}
