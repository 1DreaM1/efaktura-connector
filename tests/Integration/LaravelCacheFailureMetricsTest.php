<?php

namespace DreaM1\EfakturaConnector\Tests\Integration;

use DreaM1\EfakturaConnector\Core\CircuitBreaker\CircuitBreakerPolicy;
use DreaM1\EfakturaConnector\Core\Configuration\ProviderBaseUrl;
use DreaM1\EfakturaConnector\Core\Retry\RetryDeadline;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Authentication\OAuthAccessToken;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Authentication\SapiToken;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Http\Protocol\HttpHeaderLine;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Http\Protocol\HttpHeaderName;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Http\Protocol\HttpMethod;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Metrics\CacheFailureMetricsInterface;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Metrics\ConnectorMetricsInterface;
use DreaM1\EfakturaConnector\Laravel\Authentication\LaravelSapiTokenCache;
use DreaM1\EfakturaConnector\Laravel\Authentication\LaravelSapiTokenRefreshLock;
use DreaM1\EfakturaConnector\Laravel\Transport\LaravelCacheCircuitBreaker;
use DreaM1\EfakturaConnector\Laravel\Transport\LaravelCacheHttpRequestBackpressure;
use DreaM1\EfakturaConnector\Tests\Support\CoordinationCacheFake;
use DreaM1\EfakturaConnector\Tests\Support\HttpExchangeRequestFactory;
use DreaM1\EfakturaConnector\Tests\Support\RecordingConnectorMetrics;
use Illuminate\Container\Container;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Facade;
use PHPUnit\Framework\TestCase;

/** A shared-cache outage keeps coordination running, but must be visible to a metrics sink that asks for it. */
final class LaravelCacheFailureMetricsTest extends TestCase
{
    private CoordinationCacheFake $cache;

    /** Installs a failing-capable cache fake behind the Cache facade. */
    protected function setUp(): void
    {
        parent::setUp();
        Facade::setFacadeApplication(new Container());
        $this->cache = new CoordinationCacheFake();
        Cache::swap($this->cache);
    }

    /** Clears the facade override so it cannot leak into other tests. */
    protected function tearDown(): void
    {
        Cache::clearResolvedInstance('cache');
        Facade::setFacadeApplication(null);
        parent::tearDown();
    }

    public function testTokenCacheReportsReadAndWriteOutages(): void
    {
        $metrics = new RecordingConnectorMetrics();
        $cache   = new LaravelSapiTokenCache(null, $metrics);
        $token   = new SapiToken(OAuthAccessToken::fromString('synthetic-access-token'), time() + 300);

        $this->cache->failReads();

        $this->assertNull($cache->load('efaktura.test.token'));
        $cache->store('efaktura.test.token', $token);

        $this->assertSame(
            [CacheFailureMetricsInterface::COMPONENT_TOKEN_CACHE, CacheFailureMetricsInterface::COMPONENT_TOKEN_CACHE],
            $metrics->cacheFailures
        );
    }

    public function testTokenRefreshLockReportsAnUnavailableLockAndStillRunsTheOperation(): void
    {
        $metrics = new RecordingConnectorMetrics();
        $this->cache->failLocks();

        $result = (new LaravelSapiTokenRefreshLock(null, $metrics))->synchronized('key', 60, 1, static function (): string {
            return 'refreshed';
        });

        $this->assertSame('refreshed', $result);
        $this->assertSame([CacheFailureMetricsInterface::COMPONENT_TOKEN_REFRESH_LOCK], $metrics->cacheFailures);
    }

    public function testCircuitBreakerReportsSharedStateOutagesWhileProtectingLocally(): void
    {
        $metrics = new RecordingConnectorMetrics();
        $breaker = new LaravelCacheCircuitBreaker('metrics', new CircuitBreakerPolicy(1, 60, 30), null, null, $metrics);
        $this->cache->failLocks();

        $permit = $breaker->acquireExecutionPermit();
        $this->assertNotNull($permit);
        $breaker->recordFailureForPermit($permit);

        $this->assertTrue($breaker->isOpen());
        $this->assertNotSame([], $metrics->cacheFailures);
        $this->assertSame(
            [CacheFailureMetricsInterface::COMPONENT_CIRCUIT_BREAKER],
            array_values(array_unique($metrics->cacheFailures))
        );
    }

    public function testBackpressureReportsReadAndWriteOutages(): void
    {
        $metrics      = new RecordingConnectorMetrics();
        $backpressure = new LaravelCacheHttpRequestBackpressure(null, $metrics);
        $request      = HttpExchangeRequestFactory::create(
            HttpMethod::fromString('GET'),
            '/v1/send',
            [HttpHeaderLine::fromParts(HttpHeaderName::authorization(), 'Bearer synthetic-token')],
            null,
            [],
            null,
            ProviderBaseUrl::fromString('https://provider.synthetic.test')
        );

        $this->cache->failReads();

        $this->assertNull($backpressure->blockedUntil($request));
        $this->cache->failReads(false);
        $this->cache->failLocks();
        $backpressure->deferUntil($request, RetryDeadline::fromUnixTimestamp(time() + 30));

        $this->assertSame(
            [CacheFailureMetricsInterface::COMPONENT_BACKPRESSURE, CacheFailureMetricsInterface::COMPONENT_BACKPRESSURE],
            $metrics->cacheFailures
        );
    }

    public function testHealthyCacheReportsNothing(): void
    {
        $metrics = new RecordingConnectorMetrics();
        $cache   = new LaravelSapiTokenCache(null, $metrics);

        $this->assertNull($cache->load('efaktura.test.absent'));

        $this->assertSame([], $metrics->cacheFailures);
    }

    public function testMetricsSinkWithoutTheOptionalInterfaceIsLeftAlone(): void
    {
        $metrics = new class () implements ConnectorMetricsInterface {
            public function recordApiRequest(string $method, ?int $statusCode, float $durationSeconds, string $outcome): void
            {
            }

            public function recordTokenCacheEvent(string $event): void
            {
            }

            public function recordSchematronValidation(float $durationSeconds, string $outcome, int $rulesetCount): void
            {
            }
        };
        $this->cache->failReads();

        $this->assertNull((new LaravelSapiTokenCache(null, $metrics))->load('efaktura.test.token'));
    }
}
