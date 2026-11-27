<?php

namespace DreaM1\EfakturaConnector\Tests\Unit\Infrastructure\EFaktura\Api\Transport;

use DreaM1\EfakturaConnector\Core\CircuitBreaker\CircuitBreakerExecutionPermit;
use DreaM1\EfakturaConnector\Core\CircuitBreaker\CircuitBreakerInterface;
use DreaM1\EfakturaConnector\Core\CircuitBreaker\InMemoryCircuitBreaker;
use DreaM1\EfakturaConnector\Core\Exceptions\ProviderApiException;
use DreaM1\EfakturaConnector\Core\Exceptions\ProviderConnectionException;
use DreaM1\EfakturaConnector\Core\Exceptions\RequestDeferredException;
use DreaM1\EfakturaConnector\Core\Retry\RetryDeadline;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Transport\Execution\CircuitBreakerHttpRequestExecutor;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Transport\Execution\HttpRequestExecutorInterface;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Transport\HttpExchangeRequest;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Transport\HttpExchangeResult;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Http\Protocol\HttpMethod;
use DreaM1\EfakturaConnector\Tests\Support\HttpExchangeRequestFactory;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/** Tests HTTP request executor behavior when circuit breaker permits are applied. */
class CircuitBreakerHttpRequestExecutorTest extends TestCase
{
    public function testThrowsWhenCircuitIsOpen(): void
    {
        $breaker = $this->createMock(CircuitBreakerInterface::class);
        $breaker->method('acquireExecutionPermit')->willReturn(null);
        $inner = $this->createMock(HttpRequestExecutorInterface::class);
        $inner->expects($this->never())->method('execute');

        $this->expectException(ProviderApiException::class);

        (new CircuitBreakerHttpRequestExecutor($inner, $breaker))->execute($this->request());
    }

    public function testRecordsSuccessOnSuccessfulResponse(): void
    {
        $permit  = $this->permit();
        $breaker = $this->admitting($permit);
        $breaker->expects($this->once())->method('recordSuccessForPermit')->with($permit);
        $breaker->expects($this->never())->method('recordFailureForPermit');
        $breaker->expects($this->never())->method('recordNeutralForPermit');
        $inner = $this->createMock(HttpRequestExecutorInterface::class);
        $inner->method('execute')->willReturn($this->result(200));

        (new CircuitBreakerHttpRequestExecutor($inner, $breaker))->execute($this->request());
    }

    public function testReportsOutcomeUsingTheAdmittedGenerationPermit(): void
    {
        $permit  = new CircuitBreakerExecutionPermit('generation-four', false);
        $breaker = $this->createMock(CircuitBreakerInterface::class);
        $breaker->expects($this->once())->method('acquireExecutionPermit')->willReturn($permit);
        $breaker->expects($this->once())->method('recordSuccessForPermit')->with($permit);

        $inner = $this->createMock(HttpRequestExecutorInterface::class);
        $inner->expects($this->once())->method('execute')->willReturn($this->result(200));

        (new CircuitBreakerHttpRequestExecutor($inner, $breaker))->execute($this->request());
    }

    public function testRecordsSuccessOn4xxResponse(): void
    {
        $permit  = $this->permit();
        $breaker = $this->admitting($permit);
        $breaker->expects($this->once())->method('recordSuccessForPermit')->with($permit);
        $inner = $this->createMock(HttpRequestExecutorInterface::class);
        $inner->method('execute')->willReturn($this->result(422));

        (new CircuitBreakerHttpRequestExecutor($inner, $breaker))->execute($this->request());
    }

    public function testRecordsFailureOn5xxResponse(): void
    {
        $permit  = $this->permit();
        $breaker = $this->admitting($permit);
        $breaker->expects($this->once())->method('recordFailureForPermit')->with($permit);
        $breaker->expects($this->never())->method('recordSuccessForPermit');
        $inner = $this->createMock(HttpRequestExecutorInterface::class);
        $inner->method('execute')->willReturn($this->result(503));

        (new CircuitBreakerHttpRequestExecutor($inner, $breaker))->execute($this->request());
    }

    public function testRecordsFailureOnInvalidStatusProcessedAsServerError(): void
    {
        $permit  = $this->permit();
        $breaker = $this->admitting($permit);
        $breaker->expects($this->once())->method('recordFailureForPermit')->with($permit);
        $breaker->expects($this->never())->method('recordSuccessForPermit');
        $inner = $this->createMock(HttpRequestExecutorInterface::class);
        $inner->method('execute')->willReturn($this->result(600));

        (new CircuitBreakerHttpRequestExecutor($inner, $breaker))->execute($this->request());
    }

    public function testRecordsFailureAndRethrowsOnNetworkException(): void
    {
        $permit  = $this->permit();
        $breaker = $this->admitting($permit);
        $breaker->expects($this->once())->method('recordFailureForPermit')->with($permit);
        $curlException = new ProviderConnectionException('timeout');
        $inner         = $this->createMock(HttpRequestExecutorInterface::class);
        $inner->method('execute')->willThrowException($curlException);

        $this->expectException(ProviderApiException::class);

        (new CircuitBreakerHttpRequestExecutor($inner, $breaker))->execute($this->request());
    }

    public function testReturns5xxResponseAfterRecordingFailure(): void
    {
        $breaker = $this->admitting($this->permit());
        $breaker->method('recordFailureForPermit');
        $inner = $this->createMock(HttpRequestExecutorInterface::class);
        $inner->method('execute')->willReturn($this->result(500));

        $result = (new CircuitBreakerHttpRequestExecutor($inner, $breaker))->execute($this->request());

        $this->assertSame(500, $result->rawStatusCode());
    }

    public function testRateLimitIsNeutral(): void
    {
        $permit  = $this->permit();
        $breaker = $this->admitting($permit);
        $breaker->expects($this->once())->method('recordNeutralForPermit')->with($permit);
        $breaker->expects($this->never())->method('recordSuccessForPermit');
        $breaker->expects($this->never())->method('recordFailureForPermit');
        $inner = $this->createMock(HttpRequestExecutorInterface::class);
        $inner->method('execute')->willReturn($this->result(429));

        (new CircuitBreakerHttpRequestExecutor($inner, $breaker))->execute($this->request());
    }

    public function testRateLimitReleasesHalfOpenProbeWithoutClosingBreaker(): void
    {
        $time    = 0.0;
        $breaker = new InMemoryCircuitBreaker(static function () use (&$time): float {
            return $time;
        });
        $this->openBreaker($breaker);

        $time = 31.0;

        $inner = $this->createMock(HttpRequestExecutorInterface::class);
        $inner->expects($this->once())->method('execute')->willReturn($this->result(429));

        $result = (new CircuitBreakerHttpRequestExecutor($inner, $breaker))->execute($this->request());

        $this->assertSame(429, $result->rawStatusCode());
        $probe = $breaker->acquireExecutionPermit();
        $this->assertNotNull($probe);
        $breaker->recordFailureForPermit($probe);
        $this->assertTrue($breaker->isOpen());
    }

    public function testDeferredExecutorReleasesHalfOpenProbeAndRethrows(): void
    {
        $time    = 0.0;
        $breaker = new InMemoryCircuitBreaker(static function () use (&$time): float {
            return $time;
        });
        $this->openBreaker($breaker);

        $time = 31.0;

        $deferred = new RequestDeferredException(RetryDeadline::fromUnixTimestamp(40));
        $inner    = $this->createMock(HttpRequestExecutorInterface::class);
        $inner->expects($this->once())->method('execute')->willThrowException($deferred);

        try {
            (new CircuitBreakerHttpRequestExecutor($inner, $breaker))->execute($this->request());
            $this->fail('The deferred exception must be rethrown.');
        } catch (RequestDeferredException $exception) {
            $this->assertSame($deferred, $exception);
        }

        $probe = $breaker->acquireExecutionPermit();
        $this->assertNotNull($probe);
        $breaker->recordNeutralForPermit($probe);
    }

    /** Records enough failures through admitted permits to open a breaker with the default policy. */
    private function openBreaker(InMemoryCircuitBreaker $breaker): void
    {
        for ($i = 0; $i < 5; $i++) {
            $permit = $breaker->acquireExecutionPermit();
            $this->assertNotNull($permit);
            $breaker->recordFailureForPermit($permit);
        }
    }

    /** @return CircuitBreakerInterface&MockObject */
    private function admitting(CircuitBreakerExecutionPermit $permit): CircuitBreakerInterface
    {
        $breaker = $this->createMock(CircuitBreakerInterface::class);
        $breaker->method('acquireExecutionPermit')->willReturn($permit);

        return $breaker;
    }

    private function permit(): CircuitBreakerExecutionPermit
    {
        return new CircuitBreakerExecutionPermit('generation', false);
    }

    private function request(): HttpExchangeRequest
    {
        return HttpExchangeRequestFactory::create(HttpMethod::fromString('GET'), '/test');
    }

    private function result(int $status): HttpExchangeResult
    {
        return new HttpExchangeResult($status, '', [], 0.0);
    }
}
