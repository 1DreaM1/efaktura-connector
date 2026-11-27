<?php

namespace DreaM1\EfakturaConnector\Tests\Laravel;

use DreaM1\EfakturaConnector\Core\Exceptions\ProviderConnectionException;
use DreaM1\EfakturaConnector\Core\Exceptions\ProviderErrorResponseException;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Response\ProviderErrorEnvelopeParser;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Transport\Execution\HttpRequestExecutionLoggerInterface;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Transport\HttpExchangeRequest;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Transport\HttpExchangeResult;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Http\Protocol\HttpMethod;
use DreaM1\EfakturaConnector\Laravel\Logging\LaravelHttpRequestExecutionLogger;
use DreaM1\EfakturaConnector\Tests\Support\HttpExchangeRequestFactory;
use Illuminate\Container\Container;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Log;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/** Tests the default Laravel HTTP execution logger. */
class LaravelHttpRequestExecutionLoggerTest extends TestCase
{
    private Container $container;

    /** Installs an isolated container for the Laravel Log facade. */
    protected function setUp(): void
    {
        parent::setUp();

        $this->container = new Container();
        $this->clearLoggerFacade();
        Facade::setFacadeApplication($this->container);
    }

    /** Removes the facade root so it cannot affect other tests. */
    protected function tearDown(): void
    {
        $this->clearLoggerFacade();
        Facade::setFacadeApplication(null);

        parent::tearDown();
    }

    public function testSuccessfulResponseLogsPathWithoutQueryAndNoPayloads(): void
    {
        $loggedContext = null;
        $log           = $this->createMock(LoggerInterface::class);
        $log->expects($this->once())
            ->method('info')
            ->with(
                'eFaktura HTTP request completed',
                $this->callback(static function (array $context) use (&$loggedContext): bool {
                    $loggedContext = $context;

                    return true;
                })
            );
        $this->bindLogger($log);

        $request = HttpExchangeRequestFactory::create(
            HttpMethod::post(),
            '/v1/documents/synthetic-document-id?token=private-query-value',
            [],
            'private request payload'
        );
        $exchange = new HttpExchangeResult(201, 'private response payload', [], microtime(true));

        $this->logger()->recordResponse($request, $exchange);

        $this->assertSame(['method', 'path', 'status', 'duration_ms'], array_keys($loggedContext));
        $this->assertSame('POST', $loggedContext['method']);
        $this->assertSame('/v1/documents/synthetic-document-id', $loggedContext['path']);
        $this->assertSame(201, $loggedContext['status']);
        $this->assertGreaterThanOrEqual(0.0, $loggedContext['duration_ms']);
        $this->assertStringNotContainsString('private', json_encode($loggedContext));
    }

    public function testServerErrorResponseUsesErrorLogLevel(): void
    {
        $log = $this->createMock(LoggerInterface::class);
        $log->expects($this->once())
            ->method('error')
            ->with(
                'eFaktura HTTP request returned a server error',
                $this->callback(static function (array $context): bool {
                    return $context['method'] === 'GET'
                        && $context['status'] === 503
                        && array_key_exists('duration_ms', $context);
                })
            );
        $this->bindLogger($log);

        $this->logger()->recordResponse(
            HttpExchangeRequestFactory::create(HttpMethod::get(), '/v1/status'),
            new HttpExchangeResult(503, 'private provider body', [], microtime(true))
        );
    }

    public function testTransportFailureDoesNotLogItsExceptionMessage(): void
    {
        $loggedContext = null;
        $log           = $this->createMock(LoggerInterface::class);
        $log->expects($this->once())
            ->method('warning')
            ->with(
                'eFaktura HTTP request execution failed',
                $this->callback(static function (array $context) use (&$loggedContext): bool {
                    $loggedContext = $context;

                    return true;
                })
            );
        $this->bindLogger($log);

        $this->logger()->recordFailure(
            HttpExchangeRequestFactory::create(HttpMethod::get(), '/v1/status'),
            new ProviderConnectionException('private connection diagnostic'),
            microtime(true)
        );

        $this->assertSame(ProviderConnectionException::class, $loggedContext['exception']);
        $this->assertNull($loggedContext['status']);
        $this->assertStringNotContainsString('private', json_encode($loggedContext));
    }

    public function testErrorResponseLogsTheProviderCorrelationId(): void
    {
        $loggedContext = null;
        $log           = $this->createMock(LoggerInterface::class);
        $log->expects($this->once())
            ->method('warning')
            ->with(
                'eFaktura HTTP request returned a non-success status',
                $this->callback(static function (array $context) use (&$loggedContext): bool {
                    $loggedContext = $context;

                    return true;
                })
            );
        $this->bindLogger($log);

        $body = (string) json_encode(['error' => [
            'category'       => 'VALIDATION',
            'code'           => 'SYNTHETIC',
            'message'        => 'private provider message',
            'correlation_id' => 'corr-123',
        ]]);

        $this->logger()->recordResponse(
            HttpExchangeRequestFactory::create(HttpMethod::get(), '/v1/status'),
            new HttpExchangeResult(404, $body, [], microtime(true))
        );

        $this->assertSame('corr-123', $loggedContext['correlation_id']);
        $this->assertSame('/v1/status', $loggedContext['path']);
        $this->assertStringNotContainsString('private', (string) json_encode($loggedContext));
    }

    public function testUnsafeCorrelationIdIsNotLogged(): void
    {
        $loggedContext = null;
        $log           = $this->createMock(LoggerInterface::class);
        $log->method('warning')->willReturnCallback(
            static function (string $message, array $context) use (&$loggedContext): void {
                $loggedContext = $context;
            }
        );
        $this->bindLogger($log);

        $body = (string) json_encode(['error' => [
            'category'       => 'VALIDATION',
            'code'           => 'SYNTHETIC',
            'message'        => 'x',
            'correlation_id' => "bad id\nwith newline and spaces",
        ]]);

        $this->logger()->recordResponse(
            HttpExchangeRequestFactory::create(HttpMethod::get(), '/v1/status'),
            new HttpExchangeResult(404, $body, [], microtime(true))
        );

        $this->assertArrayNotHasKey('correlation_id', $loggedContext);
    }

    public function testMalformedErrorBodyLogsNoCorrelationId(): void
    {
        $loggedContext = null;
        $log           = $this->createMock(LoggerInterface::class);
        $log->method('warning')->willReturnCallback(
            static function (string $message, array $context) use (&$loggedContext): void {
                $loggedContext = $context;
            }
        );
        $this->bindLogger($log);

        $this->logger()->recordResponse(
            HttpExchangeRequestFactory::create(HttpMethod::get(), '/v1/status'),
            new HttpExchangeResult(404, '<html>Not found</html>', [], microtime(true))
        );

        $this->assertArrayNotHasKey('correlation_id', $loggedContext);
        $this->assertSame(404, $loggedContext['status']);
    }

    public function testProviderErrorFailureLogsPathAndCorrelationId(): void
    {
        $loggedContext = null;
        $log           = $this->createMock(LoggerInterface::class);
        $log->method('warning')->willReturnCallback(
            static function (string $message, array $context) use (&$loggedContext): void {
                $loggedContext = $context;
            }
        );
        $this->bindLogger($log);

        $body = (string) json_encode(['error' => [
            'category'       => 'VALIDATION',
            'code'           => 'SYNTHETIC',
            'message'        => 'private',
            'correlation_id' => 'corr-456',
        ]]);

        $this->logger()->recordFailure(
            HttpExchangeRequestFactory::create(HttpMethod::get(), '/v1/documents/doc-1?x=1'),
            new ProviderErrorResponseException(409, $body, [], null, ProviderErrorEnvelopeParser::parse($body)),
            microtime(true)
        );

        $this->assertSame('/v1/documents/doc-1', $loggedContext['path']);
        $this->assertSame('corr-456', $loggedContext['correlation_id']);
        $this->assertSame(ProviderErrorResponseException::class, $loggedContext['exception']);
        $this->assertStringNotContainsString('private', (string) json_encode($loggedContext));
    }

    private function logger(): HttpRequestExecutionLoggerInterface
    {
        return new LaravelHttpRequestExecutionLogger();
    }

    /** Binds the mocked logger under the facade keys used by Laravel 5.6 and newer releases. */
    private function bindLogger(LoggerInterface $logger): void
    {
        $this->container->instance('log', $logger);
        $this->container->instance(LoggerInterface::class, $logger);
    }

    /** Clears both historical and current Log facade cache keys. */
    private function clearLoggerFacade(): void
    {
        Log::clearResolvedInstance('log');
        Log::clearResolvedInstance(LoggerInterface::class);
    }
}
