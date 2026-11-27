<?php

namespace DreaM1\EfakturaConnector\Tests\Unit\Infrastructure\EFaktura\Api\Transport;

use DreaM1\EfakturaConnector\Core\CircuitBreaker\CircuitBreakerPolicy;
use DreaM1\EfakturaConnector\Core\CircuitBreaker\InMemoryCircuitBreaker;
use DreaM1\EfakturaConnector\Core\Configuration\ConnectorRuntimeConfiguration;
use DreaM1\EfakturaConnector\Core\Exceptions\ProviderConnectionException;
use DreaM1\EfakturaConnector\Core\Exceptions\RequestDeferredException;
use DreaM1\EfakturaConnector\Core\Retry\RetryDeadline;
use DreaM1\EfakturaConnector\Core\Retry\RetryPolicy;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Transport\Coordination\HttpRequestBackpressureInterface;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Transport\Coordination\ScopedHttpRequestExecutionLockInterface;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Transport\Execution\BackpressureHttpRequestExecutor;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Transport\Execution\CurlRequestExecutor;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Transport\Execution\DefaultHttpRequestExecutorFactory;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Transport\Execution\HttpRequestExecutorInterface;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Transport\Execution\HttpRequestExecutorOptions;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Transport\Execution\RejectionObservingHttpRequestExecutor;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Transport\HttpExchangeRequest;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Transport\HttpExchangeResult;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Http\Protocol\HttpMethod;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Metrics\ConnectorMetricsInterface;
use DreaM1\EfakturaConnector\Tests\Support\FrozenClock;
use DreaM1\EfakturaConnector\Tests\Support\HttpExchangeRequestFactory;
use DreaM1\EfakturaConnector\Tests\Support\RecordingConnectorMetrics;
use DreaM1\EfakturaConnector\Tests\Support\ReflectionAccess;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionProperty;

/** Tests construction of the default HTTP request executor pipeline. */
class DefaultHttpRequestExecutorFactoryTest extends TestCase
{
    public function testOptionalResilienceLayersCanBeDisabled(): void
    {
        $baseExecutor = $this->createMock(HttpRequestExecutorInterface::class);

        $executor = DefaultHttpRequestExecutorFactory::create(
            HttpRequestExecutorOptions::defaults()
                ->withBaseExecutor($baseExecutor)
                ->withRetryEnabled(false)
                ->withCircuitBreakerEnabled(false)
                ->withBackpressureEnabled(false)
                ->withExecutionLockEnabled(false)
        );

        $this->assertSame($baseExecutor, $executor);
    }

    public function testExecutionLockCanBeProvidedToTheFactory(): void
    {
        $baseExecutor  = $this->createMock(HttpRequestExecutorInterface::class);
        $executionLock = $this->createMock(ScopedHttpRequestExecutionLockInterface::class);

        $executor = DefaultHttpRequestExecutorFactory::create(
            HttpRequestExecutorOptions::defaults()
                ->withBaseExecutor($baseExecutor)
                ->withExecutionLock($executionLock)
        );

        $backpressureExecutor = $executor;
        while (! $backpressureExecutor instanceof BackpressureHttpRequestExecutor) {
            $backpressureExecutor = $this->privatePropertyValue($backpressureExecutor, 'executor');
        }

        $this->assertSame($executionLock, $this->privatePropertyValue($backpressureExecutor, 'executionLock'));
    }

    public function testExecutionLockIsReleasedBeforeEachRetryDelay(): void
    {
        $events       = [];
        $baseExecutor = $this->createMock(HttpRequestExecutorInterface::class);
        $baseExecutor->method('execute')->willReturnCallback(static function () use (&$events): HttpExchangeResult {
            $events[] = 'attempt';

            return count(array_keys($events, 'attempt', true)) === 1
                ? new HttpExchangeResult(503, self::errorBody(true), [], microtime(true))
                : new HttpExchangeResult(200, '{}', [], microtime(true));
        });
        $executionLock = $this->createMock(ScopedHttpRequestExecutionLockInterface::class);
        $executionLock->method('synchronizedFor')->willReturnCallback(
            static function (string $scopeKey, callable $callback) use (&$events): HttpExchangeResult {
                $events[] = 'lock';
                try {
                    return $callback();
                } finally {
                    $events[] = 'unlock';
                }
            }
        );

        DefaultHttpRequestExecutorFactory::create(
            HttpRequestExecutorOptions::defaults()
                ->withBaseExecutor($baseExecutor)
                ->withExecutionLock($executionLock)
                ->withCircuitBreakerEnabled(false)
                ->withRetryPolicy(new RetryPolicy(2, 10, 100, 100))
                ->withSleepFn(static function (int $ms) use (&$events): void {
                    $events[] = 'sleep';
                })
        )->execute($this->request());

        $this->assertSame(
            ['lock', 'attempt', 'unlock', 'sleep', 'lock', 'attempt', 'unlock'],
            $events
        );
    }

    public function testCircuitBreakerCountsAWholeRetriedOperationAsOneFailure(): void
    {
        $baseExecutor = $this->createMock(HttpRequestExecutorInterface::class);
        $baseExecutor->expects($this->exactly(6))->method('execute')->willReturnCallback(
            static function (): HttpExchangeResult {
                return new HttpExchangeResult(503, self::errorBody(true), [], microtime(true));
            }
        );
        $executor = DefaultHttpRequestExecutorFactory::create(
            HttpRequestExecutorOptions::defaults()
                ->withBaseExecutor($baseExecutor)
                ->withBackpressureEnabled(false)
                ->withRetryPolicy(new RetryPolicy(3, 10, 100, 100))
                ->withCircuitBreaker(new InMemoryCircuitBreaker(null, new CircuitBreakerPolicy(2, 60, 30)))
                ->withSleepFn(static function (int $ms): void {
                })
        );

        // Three attempts per operation count as a single failure, so two operations (six attempts) open the breaker.
        $this->assertSame(503, $executor->execute($this->request())->rawStatusCode());
        $this->assertSame(503, $executor->execute($this->request())->rawStatusCode());

        $this->expectException(ProviderConnectionException::class);
        $executor->execute($this->request());
    }

    public function testRejectionsAreReportedOnlyWhenSomethingCanRejectAndAnObserverExists(): void
    {
        $base = $this->createMock(HttpRequestExecutorInterface::class);

        $observed = DefaultHttpRequestExecutorFactory::create(
            HttpRequestExecutorOptions::defaults()
                ->withBaseExecutor($base)
                ->withRetryEnabled(false)
                ->withMetrics(new RecordingConnectorMetrics())
        );
        $this->assertInstanceOf(RejectionObservingHttpRequestExecutor::class, $observed);

        $cannotReject = DefaultHttpRequestExecutorFactory::create(
            HttpRequestExecutorOptions::defaults()
                ->withBaseExecutor($base)
                ->withRetryEnabled(false)
                ->withCircuitBreakerEnabled(false)
                ->withBackpressureEnabled(false)
                ->withMetrics(new RecordingConnectorMetrics())
        );
        $this->assertNotInstanceOf(RejectionObservingHttpRequestExecutor::class, $cannotReject);

        $withoutObserver = DefaultHttpRequestExecutorFactory::create(
            HttpRequestExecutorOptions::defaults()->withBaseExecutor($base)->withRetryEnabled(false)
        );
        $this->assertNotInstanceOf(RejectionObservingHttpRequestExecutor::class, $withoutObserver);
    }
    public function testRuntimeConfigurationSetsDefaultCurlTimeouts(): void
    {
        $configuration = ConnectorRuntimeConfiguration::fromArray([
            'runtime' => [
                'http' => [
                    'connect_timeout_seconds' => 6,
                    'request_timeout_seconds' => 17,
                    'response_max_bytes'      => 1048576,
                ],
                'retry' => ['enabled' => false],
            ],
        ]);

        $executor = DefaultHttpRequestExecutorFactory::createFromRuntimeConfiguration(
            $configuration,
            HttpRequestExecutorOptions::defaults()
                ->withExecutionLock($this->immediateExecutionLock())
        );
        $baseExecutor = $this->findCurlExecutor($executor);

        $this->assertSame(6, $this->privatePropertyValue($baseExecutor, 'connectTimeoutSeconds'));
        $this->assertSame(17, $this->privatePropertyValue($baseExecutor, 'requestTimeoutSeconds'));
        $responseBodyLimit = $this->privatePropertyValue($baseExecutor, 'responseBodyLimit');
        $this->assertSame(1048576, $responseBodyLimit->bytes());
    }

    public function testRuntimeFactoryCanDisableConfiguredResilienceLayers(): void
    {
        $configuration = ConnectorRuntimeConfiguration::fromArray([
            'runtime' => [
                'retry'           => ['enabled' => false],
                'circuit_breaker' => ['enabled' => false],
                'backpressure'    => ['enabled' => false, 'execution_lock' => false],
            ],
        ]);
        $baseExecutor = $this->createMock(HttpRequestExecutorInterface::class);

        $executor = DefaultHttpRequestExecutorFactory::createFromRuntimeConfiguration(
            $configuration,
            HttpRequestExecutorOptions::defaults()->withBaseExecutor($baseExecutor)
        );

        $this->assertSame($baseExecutor, $executor);
    }

    public function testRuntimeSwitchesReplaceTheLayerFlagsOfTheOptions(): void
    {
        $configuration = ConnectorRuntimeConfiguration::fromArray([
            'runtime' => [
                'retry'           => ['enabled' => false],
                'circuit_breaker' => ['enabled' => false],
                'backpressure'    => ['enabled' => true, 'execution_lock' => false],
            ],
        ]);
        $baseExecutor = $this->createMock(HttpRequestExecutorInterface::class);

        // The options ask for a breaker and a lock, but configuration decides which layers exist.
        $executor = DefaultHttpRequestExecutorFactory::createFromRuntimeConfiguration(
            $configuration,
            HttpRequestExecutorOptions::defaults()
                ->withBaseExecutor($baseExecutor)
                ->withCircuitBreakerEnabled(true)
                ->withExecutionLockEnabled(true)
        );

        $this->assertInstanceOf(BackpressureHttpRequestExecutor::class, $executor);
        $this->assertNull($this->privatePropertyValue($executor, 'executionLock'));
    }

    public function testRuntimeRetryPolicyIsAppliedToStandaloneStack(): void
    {
        $configuration = ConnectorRuntimeConfiguration::fromArray([
            'runtime' => [
                'retry' => [
                    'enabled'             => true,
                    'max_attempts'        => 2,
                    'base_delay_ms'       => 125,
                    'max_delay_ms'        => 500,
                    'max_inline_delay_ms' => 500,
                ],
            ],
        ]);
        $baseExecutor = $this->createMock(HttpRequestExecutorInterface::class);
        $baseExecutor->expects($this->exactly(2))->method('execute')->willReturnOnConsecutiveCalls(
            new HttpExchangeResult(503, self::errorBody(true), [], microtime(true)),
            new HttpExchangeResult(200, '{}', [], microtime(true))
        );
        $delays = [];

        DefaultHttpRequestExecutorFactory::createFromRuntimeConfiguration(
            $configuration,
            HttpRequestExecutorOptions::defaults()
                ->withBaseExecutor($baseExecutor)
                ->withSleepFn(static function (int $milliseconds) use (&$delays): void {
                    $delays[] = $milliseconds;
                })
                ->withExecutionLock($this->immediateExecutionLock())
        )->execute($this->request());

        $this->assertSame([125], $delays);
    }

    public function testRuntimeCircuitBreakerPolicyIsAppliedToStandaloneStack(): void
    {
        $configuration = ConnectorRuntimeConfiguration::fromArray([
            'runtime' => [
                'retry'           => ['enabled' => false],
                'circuit_breaker' => ['failure_threshold' => 1],
            ],
        ]);
        $baseExecutor = $this->createMock(HttpRequestExecutorInterface::class);
        $baseExecutor->expects($this->once())->method('execute')->willReturn(
            new HttpExchangeResult(500, self::errorBody(false), [], microtime(true))
        );
        $executor = DefaultHttpRequestExecutorFactory::createFromRuntimeConfiguration(
            $configuration,
            HttpRequestExecutorOptions::defaults()
                ->withBaseExecutor($baseExecutor)
                ->withExecutionLock($this->immediateExecutionLock())
        );

        $this->assertSame(500, $executor->execute($this->request())->rawStatusCode());
        $this->expectException(ProviderConnectionException::class);
        $executor->execute($this->request());
    }

    public function testRuntimeRateLimitCooldownIsAppliedToStandaloneStack(): void
    {
        $configuration = ConnectorRuntimeConfiguration::fromArray([
            'runtime' => [
                'retry'        => ['enabled' => false],
                'backpressure' => ['fallback_cooldown_seconds' => 4],
            ],
        ]);
        $baseExecutor = $this->createMock(HttpRequestExecutorInterface::class);
        $baseExecutor->expects($this->once())->method('execute')->willReturn(
            new HttpExchangeResult(429, '', [], microtime(true))
        );
        $backpressure = $this->createMock(HttpRequestBackpressureInterface::class);
        $backpressure->method('blockedUntil')->willReturn(null);
        $now = time();
        $backpressure->expects($this->once())->method('deferUntil')->with(
            $this->anything(),
            $this->callback(static function (RetryDeadline $retryAt) use ($now): bool {
                $unixTimestamp = $retryAt->unixTimestamp();

                return $unixTimestamp >= $now + 4 && $unixTimestamp <= $now + 5;
            })
        );

        DefaultHttpRequestExecutorFactory::createFromRuntimeConfiguration(
            $configuration,
            HttpRequestExecutorOptions::defaults()
                ->withBaseExecutor($baseExecutor)
                ->withBackpressure($backpressure)
                ->withExecutionLock($this->immediateExecutionLock())
        )->execute($this->request());
    }

    public function testRetryableRateLimitResponseStoresCooldownBeforeRetrying(): void
    {
        $configuration = ConnectorRuntimeConfiguration::fromArray([
            'runtime' => [
                'retry' => [
                    'max_attempts'        => 2,
                    'base_delay_ms'       => 50,
                    'max_delay_ms'        => 5000,
                    'max_inline_delay_ms' => 5000,
                ],
                'backpressure' => ['fallback_cooldown_seconds' => 3],
            ],
        ]);
        $baseExecutor = $this->createMock(HttpRequestExecutorInterface::class);
        $baseExecutor->expects($this->exactly(2))->method('execute')->willReturnOnConsecutiveCalls(
            new HttpExchangeResult(429, self::errorBody(true), [], microtime(true)),
            new HttpExchangeResult(200, '{}', [], microtime(true))
        );
        $stored       = null;
        $backpressure = $this->createMock(HttpRequestBackpressureInterface::class);
        $backpressure->method('blockedUntil')->willReturnCallback(static function () use (&$stored): ?RetryDeadline {
            return $stored;
        });
        $backpressure->expects($this->once())->method('deferUntil')->with(
            $this->anything(),
            $this->callback(static function (RetryDeadline $retryAt) use (&$stored): bool {
                $stored        = $retryAt;
                $unixTimestamp = $retryAt->unixTimestamp();

                return $unixTimestamp >= time() + 2 && $unixTimestamp <= time() + 3;
            })
        );
        $delays = [];

        DefaultHttpRequestExecutorFactory::createFromRuntimeConfiguration(
            $configuration,
            HttpRequestExecutorOptions::defaults()
                ->withBaseExecutor($baseExecutor)
                ->withSleepFn(static function (int $milliseconds) use (&$delays, &$stored): void {
                    $delays[] = $milliseconds;
                    $stored   = null; // the cooldown has elapsed once the retry delay was slept
                })
                ->withBackpressure($backpressure)
                ->withExecutionLock($this->immediateExecutionLock())
        )->execute($this->request());

        $this->assertCount(1, $delays);
        $this->assertGreaterThanOrEqual(2000, $delays[0]);
    }

    public function testConfiguredClockDrivesTheDefaultInMemoryCooldown(): void
    {
        $clock        = new FrozenClock();
        $baseExecutor = $this->createMock(HttpRequestExecutorInterface::class);
        $baseExecutor->expects($this->exactly(2))->method('execute')->willReturnOnConsecutiveCalls(
            new HttpExchangeResult(429, '', [], microtime(true)),
            new HttpExchangeResult(200, '{}', [], microtime(true))
        );
        $executor = DefaultHttpRequestExecutorFactory::create(
            HttpRequestExecutorOptions::defaults()
                ->withBaseExecutor($baseExecutor)
                ->withClock($clock)
                ->withRetryEnabled(false)
                ->withCircuitBreakerEnabled(false)
                ->withExecutionLockEnabled(false)
                ->withRateLimitFallbackCooldownSeconds(30)
        );

        $this->assertSame(429, $executor->execute($this->request())->rawStatusCode());

        try {
            $executor->execute($this->request());
            $this->fail('The cooldown must still be active before the configured clock advances.');
        } catch (RequestDeferredException $exception) {
            $this->assertSame(FrozenClock::EPOCH + 30, $exception->retryAt()->unixTimestamp());
        }

        $clock->advance(30);

        $this->assertSame(200, $executor->execute($this->request())->rawStatusCode());
    }

    public function testFinalRateLimitResponseIsStoredOnlyOnceWhenRetriesAreEnabled(): void
    {
        $configuration = ConnectorRuntimeConfiguration::fromArray([
            'runtime' => ['retry' => ['max_attempts' => 1]],
        ]);
        $baseExecutor = $this->createMock(HttpRequestExecutorInterface::class);
        $baseExecutor->expects($this->once())->method('execute')->willReturn(
            new HttpExchangeResult(429, '', [], microtime(true))
        );
        $backpressure = $this->createMock(HttpRequestBackpressureInterface::class);
        $backpressure->method('blockedUntil')->willReturn(null);
        $backpressure->expects($this->once())->method('deferUntil');

        $result = DefaultHttpRequestExecutorFactory::createFromRuntimeConfiguration(
            $configuration,
            HttpRequestExecutorOptions::defaults()
                ->withBaseExecutor($baseExecutor)
                ->withBackpressure($backpressure)
                ->withExecutionLock($this->immediateExecutionLock())
        )->execute($this->request());

        $this->assertSame(429, $result->rawStatusCode());
    }

    public function testRateLimitCooldownIsStoredForRequestsThatAreNotRetrySafe(): void
    {
        $configuration = ConnectorRuntimeConfiguration::fromArray([]);
        $request       = HttpExchangeRequestFactory::create(HttpMethod::fromString('POST'), '/v1/send', [], '{}');
        $baseExecutor  = $this->createMock(HttpRequestExecutorInterface::class);
        $baseExecutor->expects($this->once())->method('execute')->willReturn(
            new HttpExchangeResult(429, '', [], microtime(true))
        );
        $backpressure = $this->createMock(HttpRequestBackpressureInterface::class);
        $backpressure->method('blockedUntil')->willReturn(null);
        $backpressure->expects($this->once())->method('deferUntil');

        $result = DefaultHttpRequestExecutorFactory::createFromRuntimeConfiguration(
            $configuration,
            HttpRequestExecutorOptions::defaults()
                ->withBaseExecutor($baseExecutor)
                ->withBackpressure($backpressure)
                ->withExecutionLock($this->immediateExecutionLock())
        )->execute($request);

        $this->assertSame(429, $result->rawStatusCode());
    }

    public function testMetricsObserveEachActualRetryAttempt(): void
    {
        $metrics  = new RecordingConnectorMetrics();
        $executor = $this->createMock(HttpRequestExecutorInterface::class);
        $executor->expects($this->exactly(2))->method('execute')->willReturnOnConsecutiveCalls(
            new HttpExchangeResult(503, self::errorBody(true), [], microtime(true)),
            new HttpExchangeResult(200, '{}', [], microtime(true))
        );

        DefaultHttpRequestExecutorFactory::create(
            HttpRequestExecutorOptions::defaults()
                ->withBaseExecutor($executor)
                ->withSleepFn(static function (int $milliseconds): void {
                })
                ->withMetrics($metrics)
        )->execute($this->request());

        $this->assertCount(2, $metrics->apiRequests);
        $this->assertSame(ConnectorMetricsInterface::API_SERVER_ERROR, $metrics->apiRequests[0]['outcome']);
        $this->assertSame(ConnectorMetricsInterface::API_SUCCESS, $metrics->apiRequests[1]['outcome']);
    }

    /**
     * Retries requests only when their retry flag is explicitly enabled.
     *
     * @param int    $status           HTTP response status.
     * @param string $body             Response body used by the test exchange.
     * @param int    $expectedAttempts Expected number of executor attempts.
     * @dataProvider providerRetryFlagCases
     */
    public function testDefaultRetryRequiresAnExplicitTrueFlag(
        int $status,
        string $body,
        int $expectedAttempts
    ): void {
        $responses = [new HttpExchangeResult($status, $body, [], microtime(true))];

        if ($expectedAttempts > 1) {
            $responses[] = new HttpExchangeResult(200, '{}', [], microtime(true));
        }

        $executor = $this->createMock(HttpRequestExecutorInterface::class);
        $executor->expects($this->exactly($expectedAttempts))
            ->method('execute')
            ->willReturnOnConsecutiveCalls(...$responses);
        $delays = [];

        DefaultHttpRequestExecutorFactory::create(
            HttpRequestExecutorOptions::defaults()
                ->withBaseExecutor($executor)
                ->withSleepFn(static function (int $milliseconds) use (&$delays): void {
                    $delays[] = $milliseconds;
                })
        )->execute($this->request());

        $this->assertCount($expectedAttempts - 1, $delays);
    }

    /**
     * Provides request retry flags and their expected retry policy outcomes.
     *
     * @return array<array-key, array<array-key, mixed>> Retry configuration cases.
     */
    public static function providerRetryFlagCases(): array
    {
        return [
            'missing error body'                  => [502, '', 1],
            'malformed error body'                => [502, 'not-json', 1],
            'missing retryable flag'              => [502, self::errorBody(null), 1],
            'retryable false'                     => [500, self::errorBody(false), 1],
            'retryable true'                      => [502, self::errorBody(true), 2],
            'locked account waits for host retry' => [423, self::errorBody(true), 1],
        ];
    }

    private function request(): HttpExchangeRequest
    {
        return HttpExchangeRequestFactory::create(HttpMethod::fromString('GET'), '/sapi/test', [], null, [], null, null, true);
    }

    private function immediateExecutionLock(): ScopedHttpRequestExecutionLockInterface
    {
        $lock = $this->createMock(ScopedHttpRequestExecutionLockInterface::class);
        $lock->method('synchronizedFor')->willReturnCallback(static function (
            string $scopeKey,
            callable $callback
        ): HttpExchangeResult {
            return $callback();
        });

        return $lock;
    }

    private function findCurlExecutor(HttpRequestExecutorInterface $executor): CurlRequestExecutor
    {
        while (! $executor instanceof CurlRequestExecutor) {
            $property = new ReflectionProperty($executor, 'executor');
            ReflectionAccess::makeAccessible($property);
            $executor = $property->getValue($executor);
        }

        return $executor;
    }

    /**
     * Returns a reflected property value for assertions about the executor wrapper chain.
     *
     * @return mixed Value stored in the requested property.
     */
    private function privatePropertyValue(object $object, string $propertyName)
    {
        $property = new ReflectionProperty($object, $propertyName);
        ReflectionAccess::makeAccessible($property);

        return $property->getValue($object);
    }

    private static function errorBody(?bool $retryable): string
    {
        $error = ['message' => 'Synthetic provider error.'];

        if ($retryable !== null) {
            $error['retryable'] = $retryable;
        }

        return (string) json_encode(['error' => $error]);
    }
}
