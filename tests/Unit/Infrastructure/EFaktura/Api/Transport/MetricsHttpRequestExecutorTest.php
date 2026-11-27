<?php

namespace DreaM1\EfakturaConnector\Tests\Unit\Infrastructure\EFaktura\Api\Transport;

use DreaM1\EfakturaConnector\Core\Configuration\HttpResponseBodyLimit;
use DreaM1\EfakturaConnector\Core\Configuration\ProviderBaseUrl;
use DreaM1\EfakturaConnector\Core\Exceptions\ProviderConnectionException;
use DreaM1\EfakturaConnector\Core\Exceptions\ProviderResponseTooLargeException;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Transport\Execution\HttpRequestExecutorInterface;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Transport\Execution\MetricsHttpRequestExecutor;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Transport\HttpExchangeRequest;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Transport\HttpExchangeResult;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Http\Protocol\HttpMethod;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Metrics\ConnectorMetricsInterface;
use DreaM1\EfakturaConnector\Tests\Support\HttpExchangeRequestFactory;
use DreaM1\EfakturaConnector\Tests\Support\RecordingConnectorMetrics;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/** Tests metrics recorded by the HTTP request executor. */
class MetricsHttpRequestExecutorTest extends TestCase
{
    public function testRecordsSuccessfulHttpExchangeWithoutChangingIt(): void
    {
        $request = HttpExchangeRequestFactory::create(
            HttpMethod::fromString('POST'),
            '/v1/documents/secret-document-id',
            [],
            'secret document payload',
            [],
            null,
            ProviderBaseUrl::fromString('https://provider.synthetic.test')
        );
        $exchange = new HttpExchangeResult(201, '{"id":"synthetic"}', [], microtime(true));
        $executor = $this->createMock(HttpRequestExecutorInterface::class);
        $executor->expects($this->once())->method('execute')->with($request)->willReturn($exchange);
        $metrics = new RecordingConnectorMetrics();

        $result = (new MetricsHttpRequestExecutor($executor, $metrics))->execute($request);

        $this->assertSame($exchange, $result);
        $this->assertCount(1, $metrics->apiRequests);
        $this->assertSame('POST', $metrics->apiRequests[0]['method']);
        $this->assertSame(201, $metrics->apiRequests[0]['status_code']);
        $this->assertSame(ConnectorMetricsInterface::API_SUCCESS, $metrics->apiRequests[0]['outcome']);
        $this->assertGreaterThanOrEqual(0.0, $metrics->apiRequests[0]['duration_seconds']);
        $this->assertStringNotContainsString('secret-document-id', json_encode($metrics->apiRequests));
        $this->assertStringNotContainsString('secret document payload', json_encode($metrics->apiRequests));
    }

    public function testRecordsRateLimitAndConnectionErrorOutcomes(): void
    {
        $metrics     = new RecordingConnectorMetrics();
        $rateLimited = new HttpExchangeResult(429, '{}', [], microtime(true));
        $executor    = $this->createMock(HttpRequestExecutorInterface::class);
        $executor->expects($this->exactly(2))->method('execute')->willReturnOnConsecutiveCalls(
            $rateLimited,
            $this->throwException(new ProviderConnectionException('Synthetic connection failure.'))
        );
        $measured = new MetricsHttpRequestExecutor($executor, $metrics);

        $measured->execute(HttpExchangeRequestFactory::create(HttpMethod::fromString('GET'), '/v1/status'));
        try {
            $measured->execute(HttpExchangeRequestFactory::create(HttpMethod::fromString('GET'), '/v1/status'));
            $this->fail('The original connection exception must propagate.');
        } catch (ProviderConnectionException $exception) {
            $this->assertSame('Synthetic connection failure.', $exception->getMessage());
        }

        $this->assertSame(ConnectorMetricsInterface::API_RATE_LIMITED, $metrics->apiRequests[0]['outcome']);
        $this->assertSame(429, $metrics->apiRequests[0]['status_code']);
        $this->assertSame(ConnectorMetricsInterface::API_CONNECTION_ERROR, $metrics->apiRequests[1]['outcome']);
        $this->assertNull($metrics->apiRequests[1]['status_code']);
    }

    /**
     * Classifies HTTP status boundary values into metric outcomes.
     *
     * @param int    $statusCode      HTTP response status to classify.
     * @param string $expectedOutcome Expected metric outcome label.
     * @dataProvider httpStatusOutcomeCases
     */
    public function testClassifiesHttpStatusBoundaries(int $statusCode, string $expectedOutcome): void
    {
        $request  = HttpExchangeRequestFactory::create(HttpMethod::fromString('GET'), '/v1/status');
        $exchange = new HttpExchangeResult($statusCode, '{}', [], microtime(true));
        $executor = $this->createMock(HttpRequestExecutorInterface::class);
        $executor->expects($this->once())->method('execute')->with($request)->willReturn($exchange);
        $metrics = new RecordingConnectorMetrics();

        $result = (new MetricsHttpRequestExecutor($executor, $metrics))->execute($request);

        $this->assertSame($exchange, $result);
        $this->assertSame($statusCode, $metrics->apiRequests[0]['status_code']);
        $this->assertSame($expectedOutcome, $metrics->apiRequests[0]['outcome']);
    }

    /**
     * Provides HTTP status boundaries and their metric outcome labels.
     *
     * @return array<string, array{int, string}>
     */
    public static function httpStatusOutcomeCases(): array
    {
        return [
            'informational status'                         => [199, ConnectorMetricsInterface::API_INFORMATIONAL],
            'first success status'                         => [200, ConnectorMetricsInterface::API_SUCCESS],
            'last success status'                          => [299, ConnectorMetricsInterface::API_SUCCESS],
            'first redirect status'                        => [300, ConnectorMetricsInterface::API_REDIRECTION],
            'unrecognized valid client status'             => [471, ConnectorMetricsInterface::API_CLIENT_ERROR],
            'rate limit status'                            => [429, ConnectorMetricsInterface::API_RATE_LIMITED],
            'last client error status'                     => [499, ConnectorMetricsInterface::API_CLIENT_ERROR],
            'first server error status'                    => [500, ConnectorMetricsInterface::API_SERVER_ERROR],
            'last server error status'                     => [599, ConnectorMetricsInterface::API_SERVER_ERROR],
            'invalid status follows server error handling' => [600, ConnectorMetricsInterface::API_SERVER_ERROR],
        ];
    }

    /**
     * Records oversized response outcomes independently of retained HTTP status.
     *
     * @param int|null $statusCode Optional response status retained with the oversized response.
     * @dataProvider responseTooLargeStatusCases
     */
    public function testRecordsOversizedResponseWithAnyRetainedStatusAsResponseTooLarge(?int $statusCode): void
    {
        $request   = HttpExchangeRequestFactory::create(HttpMethod::fromString('GET'), '/v1/documents/document-1');
        $exception = new ProviderResponseTooLargeException(
            HttpResponseBodyLimit::fromBytes(1024),
            $statusCode,
            ['content-type' => 'application/json']
        );
        $executor = $this->createMock(HttpRequestExecutorInterface::class);
        $executor->expects($this->once())->method('execute')->with($request)->willThrowException($exception);
        $metrics = new RecordingConnectorMetrics();

        try {
            (new MetricsHttpRequestExecutor($executor, $metrics))->execute($request);
            $this->fail('The response size exception must propagate.');
        } catch (ProviderResponseTooLargeException $caught) {
            $this->assertSame($exception, $caught);
        }

        $this->assertCount(1, $metrics->apiRequests);
        $this->assertSame($statusCode, $metrics->apiRequests[0]['status_code']);
        $this->assertSame(
            ConnectorMetricsInterface::API_RESPONSE_TOO_LARGE,
            $metrics->apiRequests[0]['outcome']
        );
    }

    /**
     * Provides absent and retained HTTP statuses for oversized response metrics.
     *
     * @return array<string, array{int|null}>
     */
    public static function responseTooLargeStatusCases(): array
    {
        return [
            'status unavailable'           => [null],
            'successful provider status'   => [200],
            'server error provider status' => [503],
        ];
    }

    public function testMetricsBackendFailureDoesNotChangeTheExchange(): void
    {
        $exchange = new HttpExchangeResult(200, '{}', [], microtime(true));
        $executor = $this->createMock(HttpRequestExecutorInterface::class);
        $executor->expects($this->once())->method('execute')->willReturn($exchange);
        $metrics = $this->createMock(ConnectorMetricsInterface::class);
        $metrics->expects($this->once())
            ->method('recordApiRequest')
            ->willThrowException(new RuntimeException('Synthetic metrics outage.'));

        $this->assertSame(
            $exchange,
            (new MetricsHttpRequestExecutor($executor, $metrics))->execute(HttpExchangeRequestFactory::create(HttpMethod::fromString('GET'), '/v1/status'))
        );
    }
}
