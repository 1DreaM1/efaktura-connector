<?php

namespace DreaM1\EfakturaConnector\Tests\Unit\Infrastructure\EFaktura\Api\Transport;

use DreaM1\EfakturaConnector\Core\Exceptions\ProviderErrorResponseException;
use DreaM1\EfakturaConnector\Core\Exceptions\RequestDeferredException;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Transport\Coordination\HttpRequestBackpressureKey;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Transport\Coordination\InMemoryHttpRequestBackpressure;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Transport\Coordination\ScopedHttpRequestExecutionLockInterface;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Transport\Execution\BackpressureHttpRequestExecutor;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Transport\Execution\HttpRequestExecutorInterface;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Transport\Execution\RateLimitRecordingHttpRequestExecutor;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Transport\HttpExchangeRequest;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Transport\HttpExchangeResult;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Http\Protocol\HttpHeaderLine;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Http\Protocol\HttpHeaderName;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Http\Protocol\HttpMethod;
use DreaM1\EfakturaConnector\Tests\Support\FrozenClock;
use DreaM1\EfakturaConnector\Tests\Support\HttpExchangeRequestFactory;
use PHPUnit\Framework\TestCase;

/** Tests HTTP request executor behavior while provider backpressure is active. */
class BackpressureHttpRequestExecutorTest extends TestCase
{
    public function testProviderErrorExposesRetryAfterDeadline(): void
    {
        $exception = new ProviderErrorResponseException(429, '{}', ['Retry-After' => '30']);

        $this->assertGreaterThanOrEqual(time() + 29, $exception->retryAt()->unixTimestamp());
    }

    public function test429DefersOtherRequestsForTheSameProviderAndClient(): void
    {
        $backpressure = new InMemoryHttpRequestBackpressure();
        $executor     = $this->createMock(HttpRequestExecutorInterface::class);
        $executor->expects($this->once())->method('execute')->willReturn(
            new HttpExchangeResult(429, '{}', ['retry-after' => '30'], microtime(true))
        );
        $wrapped = new BackpressureHttpRequestExecutor(
            new RateLimitRecordingHttpRequestExecutor($executor, $backpressure),
            $backpressure
        );

        $response = $wrapped->execute($this->request('/v1/first'));
        $this->assertSame(429, $response->rawStatusCode());

        try {
            $wrapped->execute($this->request('/sapi/second'));
            $this->fail('Requests during the provider cooldown must be deferred before transport.');
        } catch (RequestDeferredException $exception) {
            $this->assertGreaterThanOrEqual(time() + 29, $exception->retryAt()->unixTimestamp());
        }
    }

    public function testInjectedClockDecidesWhenTheFallbackCooldownEnds(): void
    {
        $clock        = new FrozenClock();
        $backpressure = new InMemoryHttpRequestBackpressure(static function () use ($clock): int {
            return $clock->now();
        });
        $executor = $this->createMock(HttpRequestExecutorInterface::class);
        $executor->expects($this->exactly(2))->method('execute')->willReturnOnConsecutiveCalls(
            new HttpExchangeResult(429, '{}', [], microtime(true)),
            new HttpExchangeResult(200, '{}', [], microtime(true))
        );
        $wrapped = new BackpressureHttpRequestExecutor(
            new RateLimitRecordingHttpRequestExecutor($executor, $backpressure, 10, $clock),
            $backpressure,
            null,
            false,
            $clock
        );

        $wrapped->execute($this->request('/v1/first'));

        $this->assertSame(
            FrozenClock::EPOCH + 10,
            $backpressure->blockedUntil($this->request('/v1/first'))->unixTimestamp()
        );

        $clock->advance(9);

        try {
            $wrapped->execute($this->request('/v1/second'));
            $this->fail('Requests must stay deferred until the injected clock reaches the deadline.');
        } catch (RequestDeferredException $exception) {
            $this->assertSame(FrozenClock::EPOCH + 10, $exception->retryAt()->unixTimestamp());
        }

        $clock->advance(1);

        $this->assertSame(200, $wrapped->execute($this->request('/v1/second'))->rawStatusCode());
    }

    public function test429WithoutRetryAfterUsesShortCooldown(): void
    {
        $backpressure = new InMemoryHttpRequestBackpressure();
        $executor     = $this->createMock(HttpRequestExecutorInterface::class);
        $executor->expects($this->once())->method('execute')->willReturn(
            new HttpExchangeResult(429, '{}', [], microtime(true))
        );
        $request = $this->request('/v1/test');

        (new BackpressureHttpRequestExecutor(
            new RateLimitRecordingHttpRequestExecutor($executor, $backpressure),
            $backpressure
        ))->execute($request);

        $this->assertGreaterThanOrEqual(time(), $backpressure->blockedUntil($request)->unixTimestamp());
    }

    public function testFallbackCooldownCanBeConfigured(): void
    {
        $backpressure = new InMemoryHttpRequestBackpressure();
        $executor     = $this->createMock(HttpRequestExecutorInterface::class);
        $executor->expects($this->once())->method('execute')->willReturn(
            new HttpExchangeResult(429, '{}', [], microtime(true))
        );
        $request = $this->request('/v1/configured-cooldown');

        (new BackpressureHttpRequestExecutor(
            new RateLimitRecordingHttpRequestExecutor($executor, $backpressure, 5),
            $backpressure,
            null,
            false
        ))->execute($request);

        $this->assertGreaterThanOrEqual(
            time() + 4,
            $backpressure->blockedUntil($request)->unixTimestamp()
        );
    }

    public function testScopedExecutionLockUsesTheBackpressureQuotaKey(): void
    {
        $request  = $this->request('/v1/test');
        $result   = new HttpExchangeResult(200, '{}', [], microtime(true));
        $executor = $this->createMock(HttpRequestExecutorInterface::class);
        $executor->expects($this->once())->method('execute')->with($request)->willReturn($result);

        $executionLock = $this->createMock(ScopedHttpRequestExecutionLockInterface::class);
        $executionLock->expects($this->once())
            ->method('synchronizedFor')
            ->with(HttpRequestBackpressureKey::forRequest($request), $this->isType('callable'))
            ->willReturnCallback(static function (string $scopeKey, callable $callback): HttpExchangeResult {
                return $callback();
            });
        $wrapped = new BackpressureHttpRequestExecutor(
            $executor,
            new InMemoryHttpRequestBackpressure(),
            $executionLock
        );

        $this->assertSame($result, $wrapped->execute($request));
    }

    private function request(string $path): HttpExchangeRequest
    {
        return HttpExchangeRequestFactory::create(
            HttpMethod::fromString('GET'),
            $path,
            [$this->authorizationHeader('synthetic-token')]
        );
    }

    private function authorizationHeader(string $token): HttpHeaderLine
    {
        return HttpHeaderLine::fromParts(
            HttpHeaderName::authorization(),
            'Bearer ' . $token
        );
    }
}
