<?php

namespace DreaM1\EfakturaConnector\Tests\Unit\Infrastructure\EFaktura\Api\Transport;

use DreaM1\EfakturaConnector\Core\Configuration\ProviderBaseUrl;
use DreaM1\EfakturaConnector\Core\Exceptions\ProviderApiException;
use DreaM1\EfakturaConnector\Core\Exceptions\ProviderConnectionException;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Transport\Execution\HttpRequestExecutionLoggerInterface;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Transport\Execution\HttpRequestExecutorInterface;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Transport\Execution\LoggingHttpRequestExecutor;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Transport\HttpExchangeRequest;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Transport\HttpExchangeResult;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Http\Protocol\HttpMethod;
use DreaM1\EfakturaConnector\Tests\Support\HttpExchangeRequestFactory;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/** Tests request and response logging around HTTP executor calls. */
class LoggingHttpRequestExecutorTest extends TestCase
{
    public function testLogsSuccessfulExchangeAndReturnsOriginalResponse(): void
    {
        $request  = $this->request();
        $exchange = new HttpExchangeResult(200, '{"ok":true}', [], microtime(true));
        $executor = $this->createMock(HttpRequestExecutorInterface::class);
        $executor->expects($this->once())->method('execute')->with($request)->willReturn($exchange);
        $logger = $this->createMock(HttpRequestExecutionLoggerInterface::class);
        $logger->expects($this->once())->method('recordResponse')->with($request, $exchange);

        $result = (new LoggingHttpRequestExecutor($executor, $logger))->execute($request);

        $this->assertSame($exchange, $result);
    }

    public function testLogsHttpErrorResponseBeforeReturningItToTheConnector(): void
    {
        $request  = $this->request();
        $exchange = new HttpExchangeResult(503, '{"message":"Synthetic failure"}', [], microtime(true));
        $executor = $this->createMock(HttpRequestExecutorInterface::class);
        $executor->expects($this->once())->method('execute')->willReturn($exchange);
        $logger = $this->createMock(HttpRequestExecutionLoggerInterface::class);
        $logger->expects($this->once())->method('recordResponse')->with($request, $exchange);

        $this->assertSame($exchange, (new LoggingHttpRequestExecutor($executor, $logger))->execute($request));
    }

    public function testLogsTransportFailureAndRethrowsOriginalException(): void
    {
        $request  = $this->request();
        $failure  = new ProviderConnectionException('Synthetic connection failure.');
        $executor = $this->createMock(HttpRequestExecutorInterface::class);
        $executor->expects($this->once())->method('execute')->with($request)->willThrowException($failure);
        $logger = $this->createMock(HttpRequestExecutionLoggerInterface::class);
        $logger->expects($this->once())->method('recordFailure')->with($request, $failure, $this->isType('float'));

        try {
            (new LoggingHttpRequestExecutor($executor, $logger))->execute($request);
            $this->fail('The original transport exception must be rethrown.');
        } catch (ProviderApiException $exception) {
            $this->assertSame($failure, $exception);
        }
    }

    public function testLoggingFailureDoesNotReplaceProviderResponse(): void
    {
        $request  = $this->request();
        $exchange = new HttpExchangeResult(200, '{"ok":true}', [], microtime(true));
        $executor = $this->createMock(HttpRequestExecutorInterface::class);
        $executor->expects($this->once())->method('execute')->willReturn($exchange);
        $logger = $this->createMock(HttpRequestExecutionLoggerInterface::class);
        $logger->expects($this->once())
            ->method('recordResponse')
            ->willThrowException(new RuntimeException('Synthetic logging failure.'));

        $this->assertSame($exchange, (new LoggingHttpRequestExecutor($executor, $logger))->execute($request));
    }

    public function testLoggingFailureDoesNotReplaceTransportException(): void
    {
        $request  = $this->request();
        $failure  = new ProviderConnectionException('Synthetic connection failure.');
        $executor = $this->createMock(HttpRequestExecutorInterface::class);
        $executor->expects($this->once())->method('execute')->willThrowException($failure);
        $logger = $this->createMock(HttpRequestExecutionLoggerInterface::class);
        $logger->expects($this->once())
            ->method('recordFailure')
            ->willThrowException(new RuntimeException('Synthetic logging failure.'));

        try {
            (new LoggingHttpRequestExecutor($executor, $logger))->execute($request);
            $this->fail('The original transport exception must be rethrown.');
        } catch (ProviderApiException $exception) {
            $this->assertSame($failure, $exception);
        }
    }

    private function request(): HttpExchangeRequest
    {
        return HttpExchangeRequestFactory::create(
            HttpMethod::fromString('GET'),
            '/v1/status',
            [],
            null,
            [],
            null,
            ProviderBaseUrl::fromString('https://provider.synthetic.test')
        );
    }
}
