<?php

namespace DreaM1\EfakturaConnector\Tests\Unit\Infrastructure\EFaktura\Api\Transport;

use DreaM1\EfakturaConnector\Core\CircuitBreaker\CircuitBreakerInterface;
use DreaM1\EfakturaConnector\Core\Configuration\ConnectorRuntimeConfiguration;
use DreaM1\EfakturaConnector\Core\Retry\RetryPolicy;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Transport\Coordination\HttpRequestBackpressureInterface;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Transport\Coordination\ScopedHttpRequestExecutionLockInterface;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Transport\Execution\DefaultHttpRequestExecutorFactory;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Transport\Execution\HttpRequestExecutionLoggerInterface;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Transport\Execution\HttpRequestExecutorInterface;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Transport\Execution\HttpRequestExecutorOptions;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Metrics\ConnectorMetricsInterface;
use PHPUnit\Framework\TestCase;

/** Tests immutable HTTP request executor option configuration. */
class HttpRequestExecutorOptionsTest extends TestCase
{
    public function testDefaultsMatchTheFactoryDefaults(): void
    {
        $options = HttpRequestExecutorOptions::defaults();

        $this->assertNull($options->logger());
        $this->assertNull($options->baseExecutor());
        $this->assertNull($options->sleepFn());
        $this->assertNull($options->circuitBreaker());
        $this->assertNull($options->backpressure());
        $this->assertNull($options->metrics());
        $this->assertNull($options->retryPolicy());
        $this->assertNull($options->executionLock());
        $this->assertTrue($options->retryEnabled());
        $this->assertTrue($options->circuitBreakerEnabled());
        $this->assertTrue($options->backpressureEnabled());
        $this->assertTrue($options->executionLockEnabled());
        $this->assertSame(
            ConnectorRuntimeConfiguration::DEFAULT_HTTP_RATE_LIMIT_COOLDOWN_SECONDS,
            $options->rateLimitFallbackCooldownSeconds()
        );
    }

    public function testWithMethodsReturnConfiguredCopies(): void
    {
        $defaults       = HttpRequestExecutorOptions::defaults();
        $logger         = $this->createMock(HttpRequestExecutionLoggerInterface::class);
        $executor       = $this->createMock(HttpRequestExecutorInterface::class);
        $circuitBreaker = $this->createMock(CircuitBreakerInterface::class);
        $backpressure   = $this->createMock(HttpRequestBackpressureInterface::class);
        $metrics        = $this->createMock(ConnectorMetricsInterface::class);
        $executionLock  = $this->createMock(ScopedHttpRequestExecutionLockInterface::class);
        $retryPolicy    = RetryPolicy::default();
        $sleepFn        = static function (int $milliseconds): void {
        };

        $options = $defaults
            ->withLogger($logger)
            ->withBaseExecutor($executor)
            ->withSleepFn($sleepFn)
            ->withCircuitBreaker($circuitBreaker)
            ->withBackpressure($backpressure)
            ->withMetrics($metrics)
            ->withRetryPolicy($retryPolicy)
            ->withExecutionLock($executionLock)
            ->withRetryEnabled(false)
            ->withCircuitBreakerEnabled(false)
            ->withBackpressureEnabled(false)
            ->withExecutionLockEnabled(false)
            ->withRateLimitFallbackCooldownSeconds(7);

        $this->assertNotSame($defaults, $options);
        $this->assertNull($defaults->logger());
        $this->assertTrue($defaults->retryEnabled());
        $this->assertSame($logger, $options->logger());
        $this->assertSame($executor, $options->baseExecutor());
        $this->assertSame($sleepFn, $options->sleepFn());
        $this->assertSame($circuitBreaker, $options->circuitBreaker());
        $this->assertSame($backpressure, $options->backpressure());
        $this->assertSame($metrics, $options->metrics());
        $this->assertSame($retryPolicy, $options->retryPolicy());
        $this->assertSame($executionLock, $options->executionLock());
        $this->assertFalse($options->retryEnabled());
        $this->assertFalse($options->circuitBreakerEnabled());
        $this->assertFalse($options->backpressureEnabled());
        $this->assertFalse($options->executionLockEnabled());
        $this->assertSame(7, $options->rateLimitFallbackCooldownSeconds());
    }

    public function testFactoryBuildsStackFromOptions(): void
    {
        $executor = $this->createMock(HttpRequestExecutorInterface::class);
        $options  = HttpRequestExecutorOptions::defaults()
            ->withBaseExecutor($executor)
            ->withRetryEnabled(false)
            ->withCircuitBreakerEnabled(false)
            ->withBackpressureEnabled(false);

        $this->assertSame($executor, DefaultHttpRequestExecutorFactory::create($options));
    }

    public function testRuntimeFactoryAppliesConfiguredRetrySettingsToOptions(): void
    {
        $configuration = ConnectorRuntimeConfiguration::fromArray([
            'runtime' => [
                'retry'           => ['enabled' => false],
                'circuit_breaker' => ['enabled' => false],
                'backpressure'    => ['enabled' => false],
            ],
        ]);
        $executor = $this->createMock(HttpRequestExecutorInterface::class);
        $options  = HttpRequestExecutorOptions::defaults()->withBaseExecutor($executor);

        $result = DefaultHttpRequestExecutorFactory::createFromRuntimeConfiguration(
            $configuration,
            $options
        );

        $this->assertSame($executor, $result);
    }
}
