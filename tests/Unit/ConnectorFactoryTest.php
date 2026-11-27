<?php

namespace DreaM1\EfakturaConnector\Tests\Unit;

use DreaM1\EfakturaConnector\ConnectorFactory;
use DreaM1\EfakturaConnector\Core\Configuration\IntegratorAccountConfiguration;
use DreaM1\EfakturaConnector\Core\Configuration\SapiClientConfiguration;
use DreaM1\EfakturaConnector\Core\Contracts\IntegratorAccountClientInterface;
use DreaM1\EfakturaConnector\Core\Contracts\SapiClientInterface;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Authentication\SapiTokenRefreshLockInterface;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Transport\Execution\HttpRequestExecutorInterface;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Transport\Execution\HttpRequestExecutorOptions;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Transport\HttpExchangeRequest;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Transport\HttpExchangeResult;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Metrics\ConnectorMetricsInterface;
use DreaM1\EfakturaConnector\Tests\Support\ApiFixtures;
use DreaM1\EfakturaConnector\Tests\Support\FrozenClock;
use DreaM1\EfakturaConnector\Tests\Support\RecordingConnectorMetrics;
use PHPUnit\Framework\TestCase;

/** Tests the standalone composition root and its optional collaborators. */
class ConnectorFactoryTest extends TestCase
{
    public function testStandaloneFactoryCreatesSapiAndIntegratorClients(): void
    {
        $factory = new ConnectorFactory();

        $sapiClient       = $factory->createSapiClient('billing', $this->sapiConfiguration());
        $integratorClient = $factory->createIntegratorAccountClient(
            IntegratorAccountConfiguration::fromArray([
                'base_url'      => 'https://provider.synthetic.test',
                'integrator_id' => 'synthetic-integrator',
                'token'         => 'synthetic-integrator-token',
            ])
        );

        $this->assertInstanceOf(SapiClientInterface::class, $sapiClient);
        $this->assertSame('billing', $sapiClient->name());
        $this->assertInstanceOf(IntegratorAccountClientInterface::class, $integratorClient);
    }

    public function testWithMethodsReturnCopiesAndLeaveTheOriginalUnchanged(): void
    {
        $factory = new ConnectorFactory();

        $this->assertNotSame($factory, $factory->withExecutorOptions(HttpRequestExecutorOptions::defaults()));
        $this->assertNotSame($factory, $factory->withMetrics(new RecordingConnectorMetrics()));
        $this->assertNotSame($factory, $factory->withClock(new FrozenClock()));
        $this->assertNotSame(
            $factory,
            $factory->withTokenRefreshLock($this->createStub(SapiTokenRefreshLockInterface::class))
        );
    }

    public function testConfiguredCollaboratorsAreUsedByCreatedSapiClients(): void
    {
        $requests     = [];
        $baseExecutor = $this->createMock(HttpRequestExecutorInterface::class);
        $baseExecutor->method('execute')->willReturnCallback(
            static function (HttpExchangeRequest $request) use (&$requests): HttpExchangeResult {
                $requests[] = $request->path();

                return count($requests) === 1
                    ? ApiFixtures::jsonExchange(ApiFixtures::token())
                    : ApiFixtures::jsonExchange(ApiFixtures::tokenStatus());
            }
        );
        $refreshLock = $this->createMock(SapiTokenRefreshLockInterface::class);
        $refreshLock->expects($this->once())->method('synchronized')->willReturnCallback(
            static function (string $key, int $lease, int $wait, callable $operation) {
                return $operation();
            }
        );
        $metrics = new RecordingConnectorMetrics();
        $clock   = new FrozenClock();

        $client = (new ConnectorFactory())
            ->withExecutorOptions(
                HttpRequestExecutorOptions::defaults()
                    ->withBaseExecutor($baseExecutor)
                    ->withExecutionLockEnabled(false)
            )
            ->withTokenRefreshLock($refreshLock)
            ->withMetrics($metrics)
            ->withClock($clock)
            ->createSapiClient('billing', $this->sapiConfiguration());

        $this->assertTrue($client->tokenHealthProvider()->getTokenStatus()->valid());
        $this->assertCount(2, $requests);
        $this->assertCount(2, $metrics->apiRequests);
        $this->assertSame([
            ConnectorMetricsInterface::TOKEN_CACHE_MISS,
            ConnectorMetricsInterface::TOKEN_REFRESH_SUCCESS,
        ], $metrics->tokenCacheEvents);
    }

    public function testExecutorOptionsMetricsTakePrecedenceForHttpRequests(): void
    {
        $baseExecutor = $this->createMock(HttpRequestExecutorInterface::class);
        $baseExecutor->method('execute')->willReturnOnConsecutiveCalls(
            ApiFixtures::jsonExchange(ApiFixtures::token()),
            ApiFixtures::jsonExchange(ApiFixtures::tokenStatus())
        );
        $optionsMetrics = new RecordingConnectorMetrics();
        $factoryMetrics = new RecordingConnectorMetrics();

        $client = (new ConnectorFactory())
            ->withExecutorOptions(
                HttpRequestExecutorOptions::defaults()
                    ->withBaseExecutor($baseExecutor)
                    ->withMetrics($optionsMetrics)
                    ->withExecutionLockEnabled(false)
            )
            ->withMetrics($factoryMetrics)
            ->createSapiClient('billing', $this->sapiConfiguration());

        $client->tokenHealthProvider()->isTokenValid();

        $this->assertNotEmpty($optionsMetrics->apiRequests);
        $this->assertSame([], $factoryMetrics->apiRequests);
        $this->assertNotEmpty($factoryMetrics->tokenCacheEvents);
    }

    private function sapiConfiguration(): SapiClientConfiguration
    {
        return new SapiClientConfiguration(
            'https://provider.synthetic.test',
            'synthetic-client',
            'synthetic-secret',
            'document:send'
        );
    }
}
