<?php

namespace DreaM1\EfakturaConnector\Tests\Unit\Infrastructure\EFaktura\Api\Transport;

use DreaM1\EfakturaConnector\Core\CircuitBreaker\CircuitBreakerInterface;
use DreaM1\EfakturaConnector\Core\Exceptions\CircuitBreakerOpenException;
use DreaM1\EfakturaConnector\Core\Exceptions\ProviderConnectionException;
use DreaM1\EfakturaConnector\Core\Exceptions\RequestDeferredException;
use DreaM1\EfakturaConnector\Core\Retry\RetryDeadline;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Transport\Execution\CircuitBreakerHttpRequestExecutor;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Transport\Execution\HttpRequestExecutionLoggerInterface;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Transport\Execution\HttpRequestExecutorInterface;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Transport\Execution\RejectionObservingHttpRequestExecutor;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Transport\HttpExchangeRequest;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Transport\HttpExchangeResult;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Http\Protocol\HttpMethod;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Metrics\ConnectorMetricsInterface;
use DreaM1\EfakturaConnector\Tests\Support\HttpExchangeRequestFactory;
use DreaM1\EfakturaConnector\Tests\Support\RecordingConnectorMetrics;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Throwable;

/** Tests reporting of requests rejected before any HTTP attempt. */
class RejectionObservingHttpRequestExecutorTest extends TestCase
{
    public function testOpenCircuitIsReportedToMetricsAndLoggerAndRethrown(): void
    {
        $breaker = $this->createMock(CircuitBreakerInterface::class);
        $breaker->method('acquireExecutionPermit')->willReturn(null);
        $inner = $this->createMock(HttpRequestExecutorInterface::class);
        $inner->expects($this->never())->method('execute');
        $metrics = new RecordingConnectorMetrics();
        $logger  = $this->createMock(HttpRequestExecutionLoggerInterface::class);
        $logger->expects($this->once())->method('recordFailure')->with(
            $this->anything(),
            $this->isInstanceOf(CircuitBreakerOpenException::class),
            $this->isType('float')
        );

        $executor = new RejectionObservingHttpRequestExecutor(
            new CircuitBreakerHttpRequestExecutor($inner, $breaker),
            $logger,
            $metrics
        );

        try {
            $executor->execute($this->request());
            $this->fail('An open circuit must reject the request.');
        } catch (ProviderConnectionException $exception) {
            $this->assertInstanceOf(CircuitBreakerOpenException::class, $exception);
        }

        $this->assertCount(1, $metrics->apiRequests);
        $this->assertSame(ConnectorMetricsInterface::API_CIRCUIT_OPEN, $metrics->apiRequests[0]['outcome']);
        $this->assertNull($metrics->apiRequests[0]['status_code']);
        $this->assertSame('GET', $metrics->apiRequests[0]['method']);
    }

    public function testDeferredRequestIsReportedAndRethrown(): void
    {
        $deferred = new RequestDeferredException(RetryDeadline::fromUnixTimestamp(100));
        $inner    = $this->createMock(HttpRequestExecutorInterface::class);
        $inner->method('execute')->willThrowException($deferred);
        $metrics = new RecordingConnectorMetrics();

        try {
            (new RejectionObservingHttpRequestExecutor($inner, null, $metrics))->execute($this->request());
            $this->fail('The deferral must be rethrown.');
        } catch (RequestDeferredException $exception) {
            $this->assertSame($deferred, $exception);
        }

        $this->assertSame(ConnectorMetricsInterface::API_DEFERRED, $metrics->apiRequests[0]['outcome']);
    }

    public function testOrdinaryResultsAndFailuresAreNotReported(): void
    {
        $result = new HttpExchangeResult(200, '{}', [], microtime(true));
        $inner  = $this->createMock(HttpRequestExecutorInterface::class);
        $inner->method('execute')->willReturnOnConsecutiveCalls(
            $result,
            $this->throwException(new ProviderConnectionException('timeout')),
            $this->throwException(new RuntimeException('local'))
        );
        $metrics = new RecordingConnectorMetrics();
        $logger  = $this->createMock(HttpRequestExecutionLoggerInterface::class);
        $logger->expects($this->never())->method('recordFailure');
        $executor = new RejectionObservingHttpRequestExecutor($inner, $logger, $metrics);

        $this->assertSame($result, $executor->execute($this->request()));

        foreach ([ProviderConnectionException::class, RuntimeException::class] as $expected) {
            try {
                $executor->execute($this->request());
                $this->fail('The failure must be rethrown.');
            } catch (Throwable $exception) {
                $this->assertInstanceOf($expected, $exception);
            }
        }

        $this->assertSame([], $metrics->apiRequests);
    }

    public function testLoggerFailureDoesNotReplaceTheRejection(): void
    {
        $deferred = new RequestDeferredException(RetryDeadline::fromUnixTimestamp(100));
        $inner    = $this->createMock(HttpRequestExecutorInterface::class);
        $inner->method('execute')->willThrowException($deferred);
        $logger = $this->createMock(HttpRequestExecutionLoggerInterface::class);
        $logger->method('recordFailure')->willThrowException(new RuntimeException('logger down'));

        $this->expectException(RequestDeferredException::class);

        (new RejectionObservingHttpRequestExecutor($inner, $logger, null))->execute($this->request());
    }

    private function request(): HttpExchangeRequest
    {
        return HttpExchangeRequestFactory::create(HttpMethod::fromString('GET'), '/test');
    }
}
