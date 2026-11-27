<?php

namespace DreaM1\EfakturaConnector\Tests\Unit\Infrastructure\EFaktura\Api\Transport;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use DreaM1\EfakturaConnector\Core\Configuration\HttpResponseBodyLimit;
use DreaM1\EfakturaConnector\Core\Configuration\ProviderBaseUrl;
use DreaM1\EfakturaConnector\Core\Documents\IdempotencyKey;
use DreaM1\EfakturaConnector\Core\Documents\OutboundDocumentPayload;
use DreaM1\EfakturaConnector\Core\Documents\SendDocumentRequest;
use DreaM1\EfakturaConnector\Core\Documents\Sha256Checksum;
use DreaM1\EfakturaConnector\Core\Exceptions\ProviderApiException;
use DreaM1\EfakturaConnector\Core\Exceptions\ProviderConnectionException;
use DreaM1\EfakturaConnector\Core\Exceptions\ProviderResponseTooLargeException;
use DreaM1\EfakturaConnector\Core\Retry\RetryDeadline;
use DreaM1\EfakturaConnector\Core\Retry\RetryPolicy;
use DreaM1\EfakturaConnector\Domain\EFaktura\Participant;
use DreaM1\EfakturaConnector\Domain\EFaktura\PeppolDocumentTypeIdentifier;
use DreaM1\EfakturaConnector\Domain\EFaktura\PeppolProcessIdentifier;
use DreaM1\EfakturaConnector\Domain\EFaktura\SubmittedDocumentId;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\ApiConnector;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Endpoints\Sapi\SendDocumentEndpoint;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Transport\Coordination\HttpRequestBackpressureInterface;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Transport\Execution\HttpRequestExecutionLoggerInterface;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Transport\Execution\HttpRequestExecutorInterface;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Transport\Execution\LoggingHttpRequestExecutor;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Transport\Execution\MetricsHttpRequestExecutor;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Transport\Execution\RetryingHttpRequestExecutor;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Transport\HttpExchangeRequest;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Transport\HttpExchangeResult;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Http\Protocol\HttpHeaderName;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Http\Protocol\HttpMethod;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Metrics\ConnectorMetricsInterface;
use DreaM1\EfakturaConnector\Support\Timestamp;
use DreaM1\EfakturaConnector\Tests\Support\ApiFixtures;
use DreaM1\EfakturaConnector\Tests\Support\FrozenClock;
use DreaM1\EfakturaConnector\Tests\Support\HttpExchangeRequestFactory;
use DreaM1\EfakturaConnector\Tests\Support\RecordingConnectorMetrics;
use DreaM1\EfakturaConnector\Tests\Support\RecordingTransport;
use DreaM1\EfakturaConnector\Tests\Support\TestConfiguration;
use PHPUnit\Framework\TestCase;

/** Tests retry decisions and attempt limits for HTTP requests. */
class RetryingHttpRequestExecutorTest extends TestCase
{
    public function testSuccessfulResponseIsReturnedImmediately(): void
    {
        $executor = $this->executorReturning($this->exchange(200));
        $result   = $this->retrying($executor)->execute($this->request());

        $this->assertSame(200, $result->rawStatusCode());
    }

    public function testConnectionFailureIsRetriedAndSucceedsOnSecondAttempt(): void
    {
        $failure  = $this->connectionFailure();
        $success  = $this->exchange(200);
        $executor = $this->executorReturningSequence([$failure, $success]);
        $delays   = [];

        $result = $this->retrying($executor, $delays)->execute($this->request());

        $this->assertSame(200, $result->rawStatusCode());
        $this->assertCount(1, $delays); // one delay between attempts
    }

    public function testOversizedResponseFailureIsNotRetried(): void
    {
        $failure  = new ProviderResponseTooLargeException(HttpResponseBodyLimit::fromBytes(1024));
        $executor = $this->createMock(HttpRequestExecutorInterface::class);
        $executor->expects($this->once())->method('execute')->willThrowException($failure);
        $delays = [];

        try {
            (new RetryingHttpRequestExecutor(
                $executor,
                new RetryPolicy(3, 1, 10),
                static function (int $milliseconds) use (&$delays): void {
                    $delays[] = $milliseconds;
                }
            ))->execute($this->request());
            $this->fail('An oversized response must not be retried.');
        } catch (ProviderResponseTooLargeException $exception) {
            $this->assertSame($failure, $exception);
        }

        $this->assertSame([], $delays);
    }

    public function testConnectionFailureExhaustsAllAttemptsAndThrows(): void
    {
        $failure  = $this->connectionFailure();
        $executor = $this->executorReturningSequence([$failure, $failure, $failure]);

        $this->expectException(ProviderApiException::class);
        $this->retrying($executor)->execute($this->request());
    }

    public function testDocumentSubmissionIsNotRetriedAfterUnknownTransportFailure(): void
    {
        $failure  = $this->connectionFailure();
        $attempts = 0;
        $delays   = [];
        $executor = $this->createMock(HttpRequestExecutorInterface::class);
        $executor->expects($this->once())->method('execute')->willReturnCallback(
            function () use (&$attempts, $failure) {
                $attempts++;
                throw $failure;
            }
        );

        try {
            $this->retrying($executor, $delays)->execute($this->documentSubmissionRequest());
            $this->fail('An unknown submission outcome must propagate without replaying the POST.');
        } catch (ProviderApiException $exception) {
            $this->assertSame($failure, $exception);
        }

        $this->assertSame(1, $attempts);
        $this->assertSame([], $delays);
    }

    public function testDocumentSubmissionIsNotRetriedAfterServerError(): void
    {
        $delays   = [];
        $executor = $this->createMock(HttpRequestExecutorInterface::class);
        $executor->expects($this->once())->method('execute')->willReturn($this->exchange(503));

        $result = $this->retrying($executor, $delays)->execute($this->documentSubmissionRequest());

        $this->assertSame(503, $result->rawStatusCode());
        $this->assertSame([], $delays);
    }

    public function testServerErrorIsRetried(): void
    {
        $delays   = [];
        $executor = $this->executorReturningSequence([
            $this->exchange(500, [], $this->errorBody(true, 'Temporary')),
            $this->exchange(200),
        ]);

        $result = $this->retrying($executor, $delays)->execute($this->request());

        $this->assertSame(200, $result->rawStatusCode());
        $this->assertCount(1, $delays);
    }

    public function testInvalidStatusIsEligibleForServerErrorRetry(): void
    {
        $delays   = [];
        $executor = $this->executorReturningSequence([
            $this->exchange(600, [], $this->errorBody(true, 'Invalid status')),
            $this->exchange(200),
        ]);

        $result = $this->retrying($executor, $delays)->execute($this->request());

        $this->assertSame(200, $result->rawStatusCode());
        $this->assertCount(1, $delays);
    }

    public function testServerErrorReturnedWhenAttemptsExhausted(): void
    {
        $executor = $this->executorReturningSequence([
            $this->exchange(503, [], $this->errorBody(true, 'Temporary')),
            $this->exchange(503, [], $this->errorBody(true, 'Temporary')),
            $this->exchange(503, [], $this->errorBody(true, 'Temporary')),
        ]);

        $result = $this->retrying($executor)->execute($this->request());

        $this->assertSame(503, $result->rawStatusCode());
    }

    public function testTooManyRequestsIsRetried(): void
    {
        $delays   = [];
        $executor = $this->executorReturningSequence([
            $this->exchange(429, [], $this->errorBody(true, 'Temporary')),
            $this->exchange(200),
        ]);

        $result = $this->retrying($executor, $delays)->execute($this->request());

        $this->assertSame(200, $result->rawStatusCode());
    }

    public function testClientErrorIsNotRetried(): void
    {
        $executor = $this->createMock(HttpRequestExecutorInterface::class);
        $executor->expects($this->once())->method('execute')->willReturn($this->exchange(400));

        $result = $this->retrying($executor)->execute($this->request());

        $this->assertSame(400, $result->rawStatusCode());
    }

    public function testNotFoundIsNotRetried(): void
    {
        $executor = $this->createMock(HttpRequestExecutorInterface::class);
        $executor->expects($this->once())->method('execute')->willReturn($this->exchange(404));

        $result = $this->retrying($executor)->execute($this->request());

        $this->assertSame(404, $result->rawStatusCode());
    }

    /**
     * Lets the host veto retrying a provider response.
     *
     * @param int $status HTTP response status under test.
     * @dataProvider retryableStatusProvider
     */
    public function testHostCanVetoRetryingAnAnswer(int $status): void
    {
        $executor = $this->createMock(HttpRequestExecutorInterface::class);
        $executor->expects($this->once())->method('execute')
            ->willReturn($this->exchange($status, [], $this->errorBody(false, 'Permanent error')));
        $seen     = [];
        $retrying = new RetryingHttpRequestExecutor(
            $executor,
            new RetryPolicy(3, 500, 30_000),
            static function (int $ms): void {
            },
            static function (HttpExchangeResult $result) use (&$seen): bool {
                $seen[] = $result->body();

                return false;
            }
        );

        $result = $retrying->execute($this->request());

        $this->assertSame($status, $result->rawStatusCode());
        $this->assertSame([$this->errorBody(false, 'Permanent error')], $seen);
    }

    /**
     * Provides HTTP statuses that the default request policy may retry.
     *
     * @return array<array-key, array<array-key, mixed>> Retryable status cases.
     */
    public function retryableStatusProvider(): array
    {
        return [
            'server error'                             => [503],
            'too many requests'                        => [429],
            'invalid status processed as server error' => [600],
        ];
    }

    public function testDefaultRetryRequiresAnExplicitRetryableFlag(): void
    {
        $delays   = [];
        $executor = $this->executorReturningSequence([
            $this->exchange(503, [], $this->errorBody(true, 'Temporary')),
            $this->exchange(200),
        ]);

        $result = $this->retrying($executor, $delays)->execute($this->request());

        $this->assertSame(200, $result->rawStatusCode());
        $this->assertCount(1, $delays);
    }

    public function testServerErrorWithNonJsonBodyIsNotRetriedByDefault(): void
    {
        $delays   = [];
        $executor = $this->createMock(HttpRequestExecutorInterface::class);
        $executor->expects($this->once())->method('execute')
            ->willReturn($this->exchange(503, [], 'Service Unavailable'));

        $result = $this->retrying($executor, $delays)->execute($this->request());

        $this->assertSame(503, $result->rawStatusCode());
        $this->assertSame([], $delays);
    }

    public function testRetryAfterHeaderOverridesExponentialDelay(): void
    {
        $delays   = [];
        $executor = $this->executorReturningSequence([
            $this->exchange(429, ['retry-after' => '2'], $this->errorBody(true, 'Temporary')),
            $this->exchange(200),
        ]);

        $this->retrying($executor, $delays)->execute($this->request());

        $this->assertCount(1, $delays);
        $this->assertSame(2000, $delays[0]); // 2 seconds × 1000 = 2000ms
    }

    public function testDefaultPolicyReturnsLongRetryAfterForHostQueueScheduling(): void
    {
        $delays   = [];
        $executor = $this->createMock(HttpRequestExecutorInterface::class);
        $executor->expects($this->once())->method('execute')->willReturn(
            $this->exchange(429, ['retry-after' => '5'], $this->errorBody(true, 'Rate limited'))
        );

        $result = (new RetryingHttpRequestExecutor(
            $executor,
            RetryPolicy::default(),
            static function (int $ms) use (&$delays): void {
                $delays[] = $ms;
            }
        ))->execute($this->request());

        $this->assertSame(429, $result->rawStatusCode());
        $this->assertSame([], $delays);
    }

    public function testRetryAfterDelaySecondsAreValidated(): void
    {
        $cases = [
            'zero'                         => ['0', 0],
            'integer header value'         => [2, 2000],
            'negative value falls back'    => ['-1', 500],
            'fraction falls back'          => ['1.5', 500],
            'malformed value falls back'   => ['tomorrow', 500],
            'invalid HTTP date falls back' => ['Mon, 30 Feb 2026 10:20:30 GMT', 500],
            'wrong type falls back'        => [[], 500],
        ];

        foreach ($cases as $name => [$header, $expectedDelay]) {
            $delays   = [];
            $executor = $this->executorReturningSequence([
                $this->exchange(429, ['retry-after' => $header], $this->errorBody(true, 'Temporary')),
                $this->exchange(200),
            ]);

            $this->retrying($executor, $delays, new RetryPolicy(2, 500, 30_000))->execute($this->request());

            $this->assertSame([$expectedDelay], $delays, $name);
        }
    }

    public function testRetryAfterHttpDatePastTimeClampsToZero(): void
    {
        $past = (new DateTimeImmutable('-1 day', new DateTimeZone('UTC')))
            ->format(DateTimeInterface::RFC7231);
        $delays   = [];
        $executor = $this->executorReturningSequence([
            $this->exchange(503, ['retry-after' => $past], $this->errorBody(true, 'Temporary')),
            $this->exchange(200),
        ]);

        $this->retrying($executor, $delays, new RetryPolicy(2, 500, 30_000))->execute($this->request());

        $this->assertSame([0], $delays);
    }

    public function testRetryAfterHttpDateIsMeasuredAgainstTheInjectedClock(): void
    {
        $clock    = new FrozenClock();
        $retryAt  = gmdate('D, d M Y H:i:s \G\M\T', FrozenClock::EPOCH + 3);
        $delays   = [];
        $executor = $this->executorReturningSequence([
            $this->exchange(503, ['retry-after' => $retryAt], $this->errorBody(true, 'Temporary')),
            $this->exchange(200),
        ]);

        (new RetryingHttpRequestExecutor(
            $executor,
            new RetryPolicy(2, 500, 30_000),
            static function (int $ms) use (&$delays): void {
                $delays[] = $ms;
            },
            null,
            null,
            $clock
        ))->execute($this->request());

        $this->assertSame([3000], $delays);
    }

    public function testConnectionFailureBackoffAboveTheInlineLimitIsRethrownWithoutSleeping(): void
    {
        $delays   = [];
        $executor = $this->createMock(HttpRequestExecutorInterface::class);
        $executor->expects($this->once())->method('execute')->willThrowException($this->connectionFailure());

        $this->expectException(ProviderConnectionException::class);

        try {
            (new RetryingHttpRequestExecutor(
                $executor,
                new RetryPolicy(3, 5000, 30_000, 1000),
                static function (int $ms) use (&$delays): void {
                    $delays[] = $ms;
                }
            ))->execute($this->request());
        } finally {
            $this->assertSame([], $delays);
        }
    }

    public function testSharedRateLimitCooldownIsMeasuredAgainstTheInjectedClock(): void
    {
        $delays   = [];
        $executor = $this->executorReturningSequence([
            $this->exchange(429, [], $this->errorBody(true, 'Rate limited')),
            $this->exchange(200),
        ]);

        (new RetryingHttpRequestExecutor(
            $executor,
            new RetryPolicy(2, 100, 30_000),
            static function (int $ms) use (&$delays): void {
                $delays[] = $ms;
            },
            null,
            $this->backpressureBlockedUntil(FrozenClock::EPOCH + 5),
            new FrozenClock()
        ))->execute($this->request());

        $this->assertSame([5000], $delays);
    }

    public function testLongRetryAfterReturnsTheResponseWithoutAnEarlyRetry(): void
    {
        $delays   = [];
        $executor = $this->createMock(HttpRequestExecutorInterface::class);
        $executor->expects($this->once())->method('execute')->willReturn(
            $this->exchange(429, ['retry-after' => '60'], $this->errorBody(true, 'Temporary'))
        );

        $result = $this->retrying($executor, $delays)->execute($this->request());

        $this->assertSame(429, $result->rawStatusCode());
        $this->assertSame([], $delays);
    }

    public function testLongRetryAfterDateReturnsTheResponseWithoutAnEarlyRetry(): void
    {
        $future = (new DateTimeImmutable('+1 day', new DateTimeZone('UTC')))
            ->format(DateTimeInterface::RFC7231);
        $delays   = [];
        $executor = $this->createMock(HttpRequestExecutorInterface::class);
        $executor->expects($this->once())->method('execute')->willReturn(
            $this->exchange(503, ['retry-after' => $future], $this->errorBody(true, 'Temporary'))
        );

        $result = $this->retrying($executor, $delays)->execute($this->request());

        $this->assertSame(503, $result->rawStatusCode());
        $this->assertSame([], $delays);
    }

    public function testExponentialDelayGrowsBetweenRetries(): void
    {
        $delays   = [];
        $executor = $this->executorReturningSequence([
            $this->exchange(500, [], $this->errorBody(true, 'Temporary')),
            $this->exchange(500, [], $this->errorBody(true, 'Temporary')),
            $this->exchange(200),
        ]);

        $this->retrying($executor, $delays, new RetryPolicy(3, 500, 30_000))->execute($this->request());

        $this->assertCount(2, $delays);
        $this->assertSame(500, $delays[0]); // retry 1: 500ms
        $this->assertSame(1000, $delays[1]); // retry 2: 1000ms
    }

    public function testLoggerAndMetricsObserveEveryResponseAndConnectionRetry(): void
    {
        $request           = $this->request();
        $serverError       = $this->exchange(503, [], $this->errorBody(true, 'Temporary'));
        $connectionFailure = $this->connectionFailure();
        $success           = $this->exchange(200);
        $baseExecutor      = $this->createMock(HttpRequestExecutorInterface::class);
        $baseExecutor->expects($this->exactly(3))->method('execute')->with($request)->willReturnOnConsecutiveCalls(
            $serverError,
            $this->throwException($connectionFailure),
            $success
        );
        $loggedResponses = [];
        $loggedFailures  = [];
        $logger          = $this->createMock(HttpRequestExecutionLoggerInterface::class);
        $logger->expects($this->exactly(2))->method('recordResponse')->willReturnCallback(
            static function (
                HttpExchangeRequest $loggedRequest,
                HttpExchangeResult $exchange
            ) use (
                &$loggedResponses
            ): void {
                $loggedResponses[] = [$loggedRequest, $exchange];
            }
        );
        $logger->expects($this->once())->method('recordFailure')->willReturnCallback(
            static function (
                HttpExchangeRequest $loggedRequest,
                ProviderApiException $exception,
                float $startedAt
            ) use (&$loggedFailures): void {
                $loggedFailures[] = [$loggedRequest, $exception, $startedAt];
            }
        );
        $metrics         = new RecordingConnectorMetrics();
        $attemptExecutor = new MetricsHttpRequestExecutor($baseExecutor, $metrics);
        $attemptExecutor = new LoggingHttpRequestExecutor($attemptExecutor, $logger);
        $retrying        = new RetryingHttpRequestExecutor(
            $attemptExecutor,
            new RetryPolicy(3, 0, 0),
            static function (int $milliseconds): void {
            }
        );

        $result = $retrying->execute($request);

        $this->assertSame($success, $result);
        $this->assertSame([$serverError, $success], array_column($loggedResponses, 1));
        $this->assertSame($request, $loggedFailures[0][0]);
        $this->assertSame($connectionFailure, $loggedFailures[0][1]);
        $this->assertSame([
            ConnectorMetricsInterface::API_SERVER_ERROR,
            ConnectorMetricsInterface::API_CONNECTION_ERROR,
            ConnectorMetricsInterface::API_SUCCESS,
        ], array_column($metrics->apiRequests, 'outcome'));
        $this->assertSame([503, null, 200], array_column($metrics->apiRequests, 'status_code'));
    }

    // ── Helpers ─────────────────────────────────────────────────────────────

    /**
     * Wraps an executor with the supplied retry policy and records requested delays.
     *
     * @param int[] $delays Retry delay values appended in milliseconds.
     */
    private function retrying(
        HttpRequestExecutorInterface $executor,
        array & $delays = [],
        ?RetryPolicy $policy = null
    ): RetryingHttpRequestExecutor {
        return new RetryingHttpRequestExecutor(
            $executor,
            $policy ?? new RetryPolicy(3, 500, 30_000),
            static function (int $ms) use (&$delays): void {
                $delays[] = $ms;
            }
        );
    }

    private function backpressureBlockedUntil(int $timestamp): HttpRequestBackpressureInterface
    {
        $backpressure = $this->createMock(HttpRequestBackpressureInterface::class);
        $backpressure->method('blockedUntil')->willReturn(RetryDeadline::fromUnixTimestamp($timestamp));

        return $backpressure;
    }

    private function request(): HttpExchangeRequest
    {
        return HttpExchangeRequestFactory::create(HttpMethod::fromString('GET'), '/v1/test');
    }

    private function documentSubmissionRequest(): HttpExchangeRequest
    {
        $payload = new OutboundDocumentPayload(
            SubmittedDocumentId::fromString('INV-SYNTHETIC-1'),
            PeppolDocumentTypeIdentifier::fromString('synthetic-document-type'),
            PeppolProcessIdentifier::fromString('synthetic-process'),
            Participant::parse('0245:2012345676'),
            Participant::parse('0245:2012345687'),
            Timestamp::fromIso8601('2026-11-15T09:30:00Z'),
            '<Invoice>synthetic</Invoice>',
            Sha256Checksum::fromContent('<Invoice>synthetic</Invoice>')
        );
        // Capture the exact request the send-document endpoint produces.
        $transport = RecordingTransport::returning(
            ApiFixtures::jsonExchange(ApiFixtures::sendDocument('doc-1'), 202)
        );
        (new SendDocumentEndpoint(new ApiConnector($transport)))->send(
            new SendDocumentRequest(
                Participant::parse('0245:2012345676'),
                $payload,
                IdempotencyKey::fromString('00000000-0000-4000-8000-000000000001')
            )
        );

        return $transport->lastRequest()
            ->withHeader(HttpHeaderName::authorization(), 'Bearer synthetic-token')
            ->withBaseUrl(ProviderBaseUrl::fromString('https://sapi.synthetic.test'));
    }

    /** @param array<string, string> $headers Response headers for the synthetic exchange. */
    private function exchange(int $status, array $headers = [], string $body = ''): HttpExchangeResult
    {
        return new HttpExchangeResult($status, $body, $headers, microtime(true));
    }

    /** Error body in the documented envelope (SapiErrorResponseEnvelope). */
    private function errorBody(bool $retryable, string $message): string
    {
        $body                       = ApiFixtures::error($message, [], 'TEMPORARY');
        $body['error']['retryable'] = $retryable;

        return (string) json_encode($body);
    }

    private function connectionFailure(): ProviderConnectionException
    {
        return new ProviderConnectionException('Synthetic connection failure.');
    }

    private function executorReturning(HttpExchangeResult $exchange): HttpRequestExecutorInterface
    {
        $executor = $this->createMock(HttpRequestExecutorInterface::class);
        $executor->method('execute')->willReturn($exchange);
        return $executor;
    }

    /**
     * @param array<HttpExchangeResult|ProviderApiException> $responses
     *                                                                  Ordered responses or provider exceptions to return.
     */
    private function executorReturningSequence(array $responses): HttpRequestExecutorInterface
    {
        $executor = $this->createMock(HttpRequestExecutorInterface::class);
        $index    = 0;
        $executor->method('execute')->willReturnCallback(
            function () use ($responses, &$index) {
                $response = $responses[$index++];

                if ($response instanceof ProviderApiException) {
                    throw $response;
                }

                return $response;
            }
        );
        return $executor;
    }
}
