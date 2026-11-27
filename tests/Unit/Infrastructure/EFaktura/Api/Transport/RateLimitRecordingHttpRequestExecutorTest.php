<?php

namespace DreaM1\EfakturaConnector\Tests\Unit\Infrastructure\EFaktura\Api\Transport;

use DreaM1\EfakturaConnector\Core\Exceptions\RequestDeferredException;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Transport\Coordination\InMemoryHttpRequestBackpressure;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Transport\Execution\BackpressureHttpRequestExecutor;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Transport\Execution\HttpRequestExecutorInterface;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Transport\Execution\RateLimitRecordingHttpRequestExecutor;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Transport\HttpExchangeRequest;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Transport\HttpExchangeResult;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Http\Protocol\HttpMethod;
use DreaM1\EfakturaConnector\Tests\Support\FrozenClock;
use DreaM1\EfakturaConnector\Tests\Support\HttpExchangeRequestFactory;
use PHPUnit\Framework\TestCase;

/** Tests the cooldown recorded after HTTP 429 responses, including its upper bound. */
class RateLimitRecordingHttpRequestExecutorTest extends TestCase
{
    private const MAX = RateLimitRecordingHttpRequestExecutor::MAX_COOLDOWN_SECONDS;

    /**
     * @dataProvider excessiveRetryAfterValues
     */
    public function testAbsurdRetryAfterIsShortenedToTheMaximumCooldown(string $retryAfter): void
    {
        [$recorder, $backpressure] = $this->recorder(new FrozenClock(), new HttpExchangeResult(
            429,
            '{}',
            ['Retry-After' => $retryAfter],
            microtime(true)
        ));

        $recorder->execute($this->request());

        $this->assertSame(
            FrozenClock::EPOCH + self::MAX,
            $backpressure->blockedUntil($this->request())->unixTimestamp()
        );
    }

    /** @return array<string, array{string}> */
    public static function excessiveRetryAfterValues(): array
    {
        return [
            'one second over the limit' => [(string) (self::MAX + 1)],
            'one day'                   => ['86400'],
            'wrong unit (milliseconds)' => ['99999999999'],
            'PHP_INT_MAX'               => [(string) PHP_INT_MAX],
            'far future HTTP date'      => ['Fri, 31 Dec 9999 23:59:59 GMT'],
        ];
    }

    /**
     * @dataProvider acceptableRetryAfterValues
     */
    public function testRetryAfterWithinTheMaximumIsRecordedUnchanged(string $retryAfter, int $expectedSeconds): void
    {
        [$recorder, $backpressure] = $this->recorder(new FrozenClock(), new HttpExchangeResult(
            429,
            '{}',
            ['Retry-After' => $retryAfter],
            microtime(true)
        ));

        $recorder->execute($this->request());

        $this->assertSame(
            FrozenClock::EPOCH + $expectedSeconds,
            $backpressure->blockedUntil($this->request())->unixTimestamp()
        );
    }

    /** @return array<string, array{string, int}> */
    public static function acceptableRetryAfterValues(): array
    {
        return [
            'seconds'                  => ['30', 30],
            'exactly the limit'        => [(string) self::MAX, self::MAX],
            'HTTP date in ten minutes' => [gmdate('D, d M Y H:i:s', FrozenClock::EPOCH + 600) . ' GMT', 600],
        ];
    }

    public function testFallbackCooldownIsAlsoBoundedByTheMaximum(): void
    {
        $clock        = new FrozenClock();
        $backpressure = new InMemoryHttpRequestBackpressure(static function () use ($clock): int {
            return $clock->now();
        });
        $executor = $this->executorReturning(new HttpExchangeResult(429, '{}', [], microtime(true)));
        $recorder = new RateLimitRecordingHttpRequestExecutor($executor, $backpressure, 86400, $clock);

        $recorder->execute($this->request());

        $this->assertSame(
            FrozenClock::EPOCH + self::MAX,
            $backpressure->blockedUntil($this->request())->unixTimestamp()
        );
    }

    public function testResponsesOtherThan429RecordNoCooldown(): void
    {
        [$recorder, $backpressure] = $this->recorder(new FrozenClock(), new HttpExchangeResult(
            503,
            '{}',
            ['Retry-After' => '30'],
            microtime(true)
        ));

        $recorder->execute($this->request());

        $this->assertNull($backpressure->blockedUntil($this->request()));
    }

    public function testRequestsAreDeferredOnlyUntilTheBoundedDeadlineAndThenProceed(): void
    {
        $clock        = new FrozenClock();
        $backpressure = new InMemoryHttpRequestBackpressure(static function () use ($clock): int {
            return $clock->now();
        });
        $executor = $this->createMock(HttpRequestExecutorInterface::class);
        $executor->expects($this->exactly(2))->method('execute')->willReturnOnConsecutiveCalls(
            new HttpExchangeResult(429, '{}', ['Retry-After' => '99999999999'], microtime(true)),
            new HttpExchangeResult(200, '{}', [], microtime(true))
        );
        $wrapped = new BackpressureHttpRequestExecutor(
            new RateLimitRecordingHttpRequestExecutor($executor, $backpressure, 1, $clock),
            $backpressure,
            null,
            false,
            $clock
        );

        $wrapped->execute($this->request());

        try {
            $wrapped->execute($this->request());
            $this->fail('The scope must be deferred during the bounded cooldown.');
        } catch (RequestDeferredException $exception) {
            $this->assertSame(FrozenClock::EPOCH + self::MAX, $exception->retryAt()->unixTimestamp());
        }

        $clock->advance(self::MAX);

        $this->assertSame(200, $wrapped->execute($this->request())->rawStatusCode());
    }

    /** @return array{0: RateLimitRecordingHttpRequestExecutor, 1: InMemoryHttpRequestBackpressure} */
    private function recorder(FrozenClock $clock, HttpExchangeResult $result): array
    {
        $backpressure = new InMemoryHttpRequestBackpressure(static function () use ($clock): int {
            return $clock->now();
        });

        return [
            new RateLimitRecordingHttpRequestExecutor($this->executorReturning($result), $backpressure, 1, $clock),
            $backpressure,
        ];
    }

    private function executorReturning(HttpExchangeResult $result): HttpRequestExecutorInterface
    {
        $executor = $this->createMock(HttpRequestExecutorInterface::class);
        $executor->method('execute')->willReturn($result);

        return $executor;
    }

    private function request(): HttpExchangeRequest
    {
        return HttpExchangeRequestFactory::create(HttpMethod::fromString('GET'), '/v1/resource');
    }
}
