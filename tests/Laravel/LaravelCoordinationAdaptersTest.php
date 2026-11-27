<?php

namespace DreaM1\EfakturaConnector\Tests\Laravel;

use DreaM1\EfakturaConnector\Core\CircuitBreaker\CircuitBreakerDefaults;
use DreaM1\EfakturaConnector\Core\CircuitBreaker\CircuitBreakerInterface;
use DreaM1\EfakturaConnector\Core\CircuitBreaker\CircuitBreakerPolicy;
use DreaM1\EfakturaConnector\Core\Configuration\ProviderBaseUrl;
use DreaM1\EfakturaConnector\Core\Configuration\SapiClientConfiguration;
use DreaM1\EfakturaConnector\Core\Exceptions\RequestCoordinationException;
use DreaM1\EfakturaConnector\Core\Retry\RetryDeadline;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Authentication\SapiTokenRefreshLockInterface;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Transport\Coordination\HttpRequestBackpressureInterface;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Transport\Coordination\HttpRequestBackpressureKey;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Transport\Coordination\ScopedHttpRequestExecutionLockInterface;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Transport\Execution\HttpRequestExecutorInterface;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Transport\Execution\RejectionObservingHttpRequestExecutor;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Transport\HttpExchangeRequest;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Transport\HttpExchangeResult;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Http\Protocol\HttpHeaderLine;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Http\Protocol\HttpHeaderName;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Http\Protocol\HttpMethod;
use DreaM1\EfakturaConnector\Laravel\Authentication\LaravelSapiTokenRefreshLock;
use DreaM1\EfakturaConnector\Laravel\EFakturaConnectorServiceProvider;
use DreaM1\EfakturaConnector\Laravel\Transport\LaravelCacheCircuitBreaker;
use DreaM1\EfakturaConnector\Laravel\Transport\LaravelCacheHttpRequestBackpressure;
use DreaM1\EfakturaConnector\Laravel\Transport\LaravelCacheHttpRequestExecutionLock;
use DreaM1\EfakturaConnector\Laravel\Transport\LaravelCacheLock;
use DreaM1\EfakturaConnector\Tests\Support\CoordinationCacheFake;
use DreaM1\EfakturaConnector\Tests\Support\HttpExchangeRequestFactory;
use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Facade;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/** Checks shared Laravel cache coordination for backpressure, circuit breaking and request locks. */
class LaravelCoordinationAdaptersTest extends TestCase
{
    private CoordinationCacheFake $cache;

    /** Installs the shared coordination cache fake before each test. */
    protected function setUp(): void
    {
        parent::setUp();
        Facade::setFacadeApplication(new Container());
        $this->cache = new CoordinationCacheFake();
        Cache::swap($this->cache);
    }

    /** Clears the cache facade override after each test. */
    protected function tearDown(): void
    {
        Cache::clearResolvedInstance('cache');
        Facade::setFacadeApplication(null);
        parent::tearDown();
    }

    public function testLaravelBackpressureShares429CooldownAcrossInstances(): void
    {
        $request = $this->request('/v1/send');
        $retryAt = RetryDeadline::fromUnixTimestamp(time() + 30);
        $first   = new LaravelCacheHttpRequestBackpressure();
        $second  = new LaravelCacheHttpRequestBackpressure();

        $first->deferUntil($request, $retryAt);

        $blockedUntil = $second->blockedUntil($this->request('/sapi/inbox'));
        $this->assertNotNull($blockedUntil);
        $this->assertSame($retryAt->unixTimestamp(), $blockedUntil->unixTimestamp());
    }

    public function testLaravelBackpressureUsesTheConfiguredCacheStore(): void
    {
        $request      = $this->request('/v1/send');
        $retryAt      = RetryDeadline::fromUnixTimestamp(time() + 30);
        $backpressure = new LaravelCacheHttpRequestBackpressure('connector-state');

        $backpressure->deferUntil($request, $retryAt);
        $blockedUntil = $backpressure->blockedUntil($request);

        $this->assertNotNull($blockedUntil);
        $this->assertSame($retryAt->unixTimestamp(), $blockedUntil->unixTimestamp());
        $this->assertSame('connector-state', $this->cache->lastStoreName());
    }

    public function testExpiredCooldownReadDoesNotDeleteANewerConcurrentCooldown(): void
    {
        $request    = $this->request('/v1/send');
        $key        = 'efaktura.http_backpressure.' . HttpRequestBackpressureKey::forRequest($request);
        $newRetryAt = RetryDeadline::fromUnixTimestamp(time() + 30);
        $this->cache->put($key, time() - 1, 60);
        $this->cache->afterNextGet(
            static function (
                CoordinationCacheFake $cache,
                string $readKey,
                $value
            ) use (
                $key,
                $newRetryAt
            ): void {
                if ($readKey === $key) {
                    $cache->put($key, $newRetryAt->unixTimestamp(), 60);
                }
            }
        );

        $backpressure = new LaravelCacheHttpRequestBackpressure();

        $this->assertNull($backpressure->blockedUntil($request));
        $blockedUntil = $backpressure->blockedUntil($request);
        $this->assertNotNull($blockedUntil);
        $this->assertSame($newRetryAt->unixTimestamp(), $blockedUntil->unixTimestamp());
    }

    public function testLaravelCircuitBreakerSharesFailuresAndReservesOneProbe(): void
    {
        $first  = new LaravelCacheCircuitBreaker('shared-client');
        $second = new LaravelCacheCircuitBreaker('shared-client');
        for ($i = 0; $i < 5; $i++) {
            $this->failOnce($first);
        }

        $this->assertTrue($second->isOpen());
        $this->assertNull($second->acquireExecutionPermit());

        $this->cache->put('efaktura.circuit_breaker.shared-client', [
            'state'           => 'OPEN',
            'failure_times'   => [],
            'opened_at'       => microtime(true) - 31,
            'probe_in_flight' => false,
        ], 90);

        $this->assertNotNull($first->acquireExecutionPermit());
        $this->assertNull($second->acquireExecutionPermit());
    }

    public function testLaravelCircuitBreakerIgnoresOutcomesFromAnOlderGeneration(): void
    {
        $policy        = new CircuitBreakerPolicy(1, 60, 30);
        $first         = new LaravelCacheCircuitBreaker('generation', $policy);
        $second        = new LaravelCacheCircuitBreaker('generation', $policy);
        $stalePermit   = $first->acquireExecutionPermit();
        $openingPermit = $second->acquireExecutionPermit();
        $this->assertNotNull($stalePermit);
        $this->assertNotNull($openingPermit);

        $second->recordFailureForPermit($openingPermit);
        $first->recordSuccessForPermit($stalePermit);

        $state = $this->cache->get('efaktura.circuit_breaker.generation');
        $this->assertSame('OPEN', $state['state']);
        $this->assertNotSame($stalePermit->generation(), $state['generation']);
    }

    public function testLaravelCircuitBreakerNeutralProbeCanRetryAndSuccessClosesCircuit(): void
    {
        $this->cache->put('efaktura.circuit_breaker.shared-client', [
            'state'           => 'OPEN',
            'failure_times'   => [],
            'opened_at'       => microtime(true) - 31,
            'probe_in_flight' => false,
        ], 90);
        $first  = new LaravelCacheCircuitBreaker('shared-client');
        $second = new LaravelCacheCircuitBreaker('shared-client');

        $probe = $first->acquireExecutionPermit();
        $this->assertNotNull($probe);
        $this->assertNull($second->acquireExecutionPermit());

        $first->recordNeutralForPermit($probe);
        $secondProbe = $second->acquireExecutionPermit();
        $this->assertNotNull($secondProbe);
        $second->recordSuccessForPermit($secondProbe);

        $this->assertFalse($first->isOpen());
        $state = $this->cache->get('efaktura.circuit_breaker.shared-client');
        $this->assertSame('CLOSED', $state['state']);
        $this->assertSame([], $state['failure_times']);
    }

    public function testLaravelCircuitBreakerFallsBackToLocalStateWhenCacheLockFails(): void
    {
        $this->cache->failLocks();
        $breaker = new LaravelCacheCircuitBreaker('cache-failure');

        for ($i = 0; $i < CircuitBreakerDefaults::FAILURE_THRESHOLD; $i++) {
            $this->failOnce($breaker);
        }

        $this->assertTrue($breaker->isOpen());
    }

    public function testLaravelCircuitBreakerReturnsToSharedStateAfterCacheRecovers(): void
    {
        $now   = time();
        $clock = static function () use (&$now): int {
            return $now;
        };
        $this->cache->failLocks();
        $policy  = new CircuitBreakerPolicy(1, 60, 30);
        $breaker = new LaravelCacheCircuitBreaker('cache-recovers', $policy, $clock);
        $this->failOnce($breaker);
        $this->assertTrue($breaker->isOpen());

        $this->cache->failLocks(false);
        $now += 6;
        $this->failOnce($breaker);

        $sharedBreaker = new LaravelCacheCircuitBreaker('cache-recovers', $policy, $clock);
        $this->assertTrue($sharedBreaker->isOpen());
        $state = $this->cache->get('efaktura.circuit_breaker.cache-recovers');
        $this->assertSame('OPEN', $state['state']);
    }

    public function testLaravelCircuitBreakerFallbackKeepsPermitGenerationAfterCacheLockFails(): void
    {
        $breaker = new LaravelCacheCircuitBreaker('mid-request-cache-failure', new CircuitBreakerPolicy(1, 60, 30));
        $permit  = $breaker->acquireExecutionPermit();
        $this->assertNotNull($permit);

        $this->cache->failLocks();
        $breaker->recordFailureForPermit($permit);

        $this->assertTrue($breaker->isOpen());
    }

    public function testLaravelCircuitBreakerResetsMalformedCachedStateBeforeTransition(): void
    {
        $this->cache->put('efaktura.circuit_breaker.malformed', [
            'state'         => 'HALF_OPEN',
            'failure_times' => [],
        ], 90);
        $breaker = new LaravelCacheCircuitBreaker('malformed');

        $this->failOnce($breaker);

        $state = $this->cache->get('efaktura.circuit_breaker.malformed');
        $this->assertSame('CLOSED', $state['state']);
        $this->assertCount(1, $state['failure_times']);
        $this->assertNull($state['opened_at']);
        $this->assertFalse($state['probe_in_flight']);
    }

    public function testCircuitBreakerFactoryScopesSharedStateToProviderConnection(): void
    {
        $first = LaravelCacheCircuitBreaker::forConnectionConfiguration(
            new SapiClientConfiguration(
                'https://provider.synthetic.test',
                'synthetic-client',
                'synthetic-secret',
                'document:send'
            )
        );
        $sameConnection = LaravelCacheCircuitBreaker::forConnectionConfiguration(
            new SapiClientConfiguration(
                'https://provider.synthetic.test',
                'synthetic-client',
                'synthetic-secret',
                'document:send'
            )
        );
        $differentBaseUrl = LaravelCacheCircuitBreaker::forConnectionConfiguration(
            new SapiClientConfiguration(
                'https://other-provider.synthetic.test',
                'synthetic-client',
                'synthetic-secret',
                'document:send'
            )
        );
        $differentClient = LaravelCacheCircuitBreaker::forConnectionConfiguration(
            new SapiClientConfiguration(
                'https://provider.synthetic.test',
                'other-client',
                'synthetic-secret',
                'document:send'
            )
        );
        $differentScope = LaravelCacheCircuitBreaker::forConnectionConfiguration(
            new SapiClientConfiguration(
                'https://provider.synthetic.test',
                'synthetic-client',
                'synthetic-secret',
                'document:receive'
            )
        );

        for ($i = 0; $i < CircuitBreakerDefaults::FAILURE_THRESHOLD; $i++) {
            $this->failOnce($first);
        }

        $this->assertTrue($first->isOpen());
        $this->assertTrue($sameConnection->isOpen());
        $this->assertFalse($differentBaseUrl->isOpen());
        $this->assertFalse($differentClient->isOpen());
        $this->assertFalse($differentScope->isOpen());
    }

    public function testLaravelTokenRefreshAdapterAcquiresAndReleasesCacheLock(): void
    {
        $lock = new LaravelSapiTokenRefreshLock();

        $result = $lock->synchronized('token-key', 60, 5, static function (): string {
            return 'token-operation';
        });

        $this->assertSame('token-operation', $result);
        $this->assertSame(1, $this->cache->lock('token-key.refresh-lock', 60)->releaseCount());
    }

    public function testLaravelTokenRefreshLockUsesTheConfiguredCacheStore(): void
    {
        $lock = new LaravelSapiTokenRefreshLock('connector-state');

        $result = $lock->synchronized('named-token-key', 60, 5, static function (): string {
            return 'token-operation';
        });

        $this->assertSame('token-operation', $result);
        $this->assertSame('connector-state', $this->cache->lastStoreName());
        $this->assertSame(1, $this->cache->lock('named-token-key.refresh-lock', 60)->releaseCount());
    }

    public function testLaravelCircuitBreakerUsesTheConfiguredCacheStore(): void
    {
        $breaker = new LaravelCacheCircuitBreaker('named-cache-state', null, null, 'connector-state');

        $this->failOnce($breaker);

        $this->assertSame('connector-state', $this->cache->lastStoreName());
        $this->assertSame(
            'CLOSED',
            $this->cache->get('efaktura.circuit_breaker.named-cache-state')['state']
        );
    }

    public function testHttpRequestExecutionLockUsesDefaultCacheStoreAndConfiguredDefaults(): void
    {
        $lock     = new LaravelCacheHttpRequestExecutionLock();
        $result   = new HttpExchangeResult(200, 'response', [], microtime(true));
        $scopeKey = 'provider-rate-limit-scope';

        $actual = $lock->synchronizedFor($scopeKey, static function () use ($result): HttpExchangeResult {
            return $result;
        });

        $cacheLock = $this->cache->lock(
            'efaktura.http_execution.' . hash('sha256', $scopeKey),
            180
        );
        $this->assertSame($result, $actual);
        $this->assertNull($this->cache->lastStoreName());
        $this->assertSame(180, $cacheLock->leaseSeconds());
        $this->assertSame(120, $cacheLock->waitSeconds());
        $this->assertSame(1, $cacheLock->releaseCount());
    }

    public function testHttpRequestExecutionLockUsesConfiguredCacheStoreAndTimeouts(): void
    {
        $lock     = new LaravelCacheHttpRequestExecutionLock('http-locks', 240, 8);
        $scopeKey = 'configured-provider-scope';
        $result   = new HttpExchangeResult(204, '', [], microtime(true));

        $lock->synchronizedFor($scopeKey, static function () use ($result): HttpExchangeResult {
            return $result;
        });

        $cacheLock = $this->cache->lock(
            'efaktura.http_execution.' . hash('sha256', $scopeKey),
            240
        );
        $this->assertSame('http-locks', $this->cache->lastStoreName());
        $this->assertSame(240, $cacheLock->leaseSeconds());
        $this->assertSame(8, $cacheLock->waitSeconds());
        $this->assertSame(1, $cacheLock->releaseCount());
    }

    public function testHttpRequestExecutionLockFailsClosedWhenCacheLockCannotBeAcquired(): void
    {
        $this->cache->failLocks();
        $calls = 0;

        try {
            (new LaravelCacheHttpRequestExecutionLock())->synchronizedFor(
                'cache-failure-scope',
                static function () use (&$calls): HttpExchangeResult {
                    $calls++;

                    return new HttpExchangeResult(200, '', [], microtime(true));
                }
            );
            $this->fail('Expected cache lock acquisition to fail.');
        } catch (RequestCoordinationException $exception) {
            $this->assertInstanceOf(RuntimeException::class, $exception->getPrevious());
            $this->assertSame('Synthetic cache lock failure.', $exception->getPrevious()->getMessage());
        }

        $this->assertSame(0, $calls);
    }

    public function testLaravelTokenRefreshDoesNotRepeatAnOperationFailure(): void
    {
        $lock  = new LaravelSapiTokenRefreshLock();
        $calls = 0;

        try {
            $lock->synchronized('token-failure-key', 60, 5, static function () use (&$calls): void {
                $calls++;
                throw new RuntimeException('Synthetic token refresh failure.');
            });
            $this->fail('Expected the token refresh operation to fail.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Synthetic token refresh failure.', $exception->getMessage());
        }

        $this->assertSame(1, $calls);
    }

    public function testLaravelTokenRefreshRunsOnceWhenAtomicLockCannotBeAcquired(): void
    {
        $this->cache->failLocks();
        $lock  = new LaravelSapiTokenRefreshLock();
        $calls = 0;

        $result = $lock->synchronized(
            'token-lock-failure-key',
            60,
            1,
            static function () use (&$calls): string {
                $calls++;

                return 'token-refresh-operation';
            }
        );

        $this->assertSame('token-refresh-operation', $result);
        $this->assertSame(1, $calls);
    }

    public function testLaravelCacheLockDoesNotBypassAnAtomicLockFailure(): void
    {
        $this->cache->failLocks();
        $calls = 0;

        try {
            LaravelCacheLock::synchronized(
                'atomic-lock-failure-key',
                static function () use (&$calls): void {
                    $calls++;
                },
                5,
                0
            );
            $this->fail('Expected atomic cache lock acquisition to fail.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Synthetic cache lock failure.', $exception->getMessage());
        }

        $this->assertSame(0, $calls);
    }

    public function testLaravelCacheLockUsesLocalFallbackWhenStoreHasNoLockApi(): void
    {
        $cache = new class () {
            /** Returns this simple repository as the selected store. */
            public function store(?string $name = null): self
            {
                return $this;
            }

            /** Returns this simple object as the cache store. */
            public function getStore(): self
            {
                return $this;
            }
        };
        Cache::swap($cache);
        $calls = 0;

        $result = LaravelCacheLock::synchronized('compatibility-lock-key', static function () use (&$calls): string {
            $calls++;

            return 'local-lock-operation';
        });

        $this->assertSame('local-lock-operation', $result);
        $this->assertSame(1, $calls);
    }

    public function testLaravelContainerUsesSharedCoordinationAdaptersInDefaultExecutor(): void
    {
        $container = new Application(sys_get_temp_dir());
        $container->instance('config', new Repository([
            'efaktura-connector' => [
                'clients' => [
                    'default' => [
                        'base_url'      => 'https://provider.synthetic.test',
                        'client_id'     => 'synthetic-client',
                        'client_secret' => 'synthetic-secret',
                    ],
                ],
            ],
        ]));
        (new EFakturaConnectorServiceProvider($container))->register();

        $this->assertInstanceOf(
            LaravelSapiTokenRefreshLock::class,
            $container->make(SapiTokenRefreshLockInterface::class)
        );
        $this->assertInstanceOf(
            LaravelCacheHttpRequestBackpressure::class,
            $container->make(HttpRequestBackpressureInterface::class)
        );
        $this->assertInstanceOf(
            LaravelCacheHttpRequestExecutionLock::class,
            $container->make(ScopedHttpRequestExecutionLockInterface::class)
        );
        $this->assertInstanceOf(
            LaravelCacheCircuitBreaker::class,
            $container->make(CircuitBreakerInterface::class)
        );
        $this->assertInstanceOf(
            RejectionObservingHttpRequestExecutor::class,
            $container->make(HttpRequestExecutorInterface::class)
        );
    }

    public function testLaravelContainerPassesRequestExecutionLockSettingsToTheCacheAdapter(): void
    {
        $container = new Application(sys_get_temp_dir());
        $container->instance('config', new Repository([
            'efaktura-connector' => [
                'laravel' => [
                    'request_execution' => [
                        'execution_lock' => [
                            'driver'        => 'cache',
                            'store'         => 'http-locks',
                            'lease_seconds' => 240,
                            'wait_seconds'  => 8,
                        ],
                    ],
                ],
            ],
        ]));
        (new EFakturaConnectorServiceProvider($container))->register();

        $lock = $container->make(ScopedHttpRequestExecutionLockInterface::class);
        $this->assertInstanceOf(LaravelCacheHttpRequestExecutionLock::class, $lock);

        $scopeKey = 'configured-request-execution-scope';
        $lock->synchronizedFor($scopeKey, static function (): HttpExchangeResult {
            return new HttpExchangeResult(200, '', [], microtime(true));
        });

        $cacheLock = $this->cache->lock(
            'efaktura.http_execution.' . hash('sha256', $scopeKey),
            240
        );
        $this->assertSame('http-locks', $this->cache->lastStoreName());
        $this->assertSame(8, $cacheLock->waitSeconds());
        $this->assertSame(1, $cacheLock->releaseCount());
    }

    /**
     * Creates a synthetic GET request for the supplied provider path.
     *
     * @param  string              $path Provider API path.
     * @return HttpExchangeRequest Request scoped to the synthetic provider.
     */
    private function request(string $path): HttpExchangeRequest
    {
        return HttpExchangeRequestFactory::create(
            HttpMethod::fromString('GET'),
            $path,
            [HttpHeaderLine::fromParts(HttpHeaderName::authorization(), 'Bearer synthetic-token')],
            null,
            [],
            null,
            ProviderBaseUrl::fromString('https://provider.synthetic.test'),
            null
        );
    }

    /** Admits one request and reports it as a provider failure. */
    private function failOnce(CircuitBreakerInterface $breaker): void
    {
        $permit = $breaker->acquireExecutionPermit();
        $this->assertNotNull($permit);
        $breaker->recordFailureForPermit($permit);
    }
}
