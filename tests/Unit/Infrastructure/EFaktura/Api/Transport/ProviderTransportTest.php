<?php

namespace DreaM1\EfakturaConnector\Tests\Unit\Infrastructure\EFaktura\Api\Transport;

use DreaM1\EfakturaConnector\Core\Exceptions\ProviderApiException;
use DreaM1\EfakturaConnector\Core\Exceptions\ProviderConnectionException;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Transport\Coordination\HttpRequestBackpressureKey;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Transport\Execution\HttpRequestExecutorInterface;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Transport\HttpExchangeRequest;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Transport\HttpExchangeResult;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Transport\HttpTransportInterface;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Transport\ProviderTransport;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Http\Protocol\HttpHeaderLine;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Http\Protocol\HttpHeaderName;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Http\Protocol\HttpMethod;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Http\Protocol\MediaType;
use DreaM1\EfakturaConnector\Tests\Support\HttpExchangeRequestFactory;
use DreaM1\EfakturaConnector\Tests\Support\TestConfiguration;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/** Tests provider transport request execution and response mapping. */
class ProviderTransportTest extends TestCase
{
    public function testTransportExposesOneTypedSendMethod(): void
    {
        $this->assertSame(['send'], get_class_methods(HttpTransportInterface::class));

        $method = new ReflectionMethod(HttpTransportInterface::class, 'send');

        $this->assertSame(HttpExchangeRequest::class, $method->getParameters()[0]->getType()->getName());
        $this->assertSame(HttpExchangeResult::class, $method->getReturnType()->getName());
    }

    public function testTransportReturnsTheRawHttpExchange(): void
    {
        $exchange = new HttpExchangeResult(
            200,
            '{"valid":true}',
            ['content-type' => 'application/json'],
            microtime(true)
        );
        $executor = $this->createMock(HttpRequestExecutorInterface::class);
        $executor->expects($this->once())
            ->method('execute')
            ->with($this->callback(function (HttpExchangeRequest $request) {
                $this->assertSame('POST', $request->httpMethod()->value());
                $this->assertSame('https://sapi.synthetic.test', $request->baseUrlOrNull()->value());
                $this->assertSame('/v1/validate', $request->path());
                $this->assertSame('X-Test: synthetic', $request->headers()->all()[0]->toString());
                $this->assertSame('{"input":true}', $request->body());
                $this->assertSame(MediaType::APPLICATION_JSON, $request->acceptHeader());

                return true;
            }))
            ->willReturn($exchange);

        $result = $this->transport($executor)->send(
            HttpExchangeRequestFactory::create(HttpMethod::fromString('POST'), '/v1/validate', [
                HttpHeaderLine::fromParts(HttpHeaderName::fromString('X-Test'), 'synthetic'),
            ], '{"input":true}')
        );

        $this->assertSame('{"valid":true}', $result->body());
        $this->assertSame(200, $result->rawStatusCode());
        $this->assertSame('application/json', $result->headers()['content-type']);
    }

    public function testTransportAttachesConfiguredBaseUrlToRequest(): void
    {
        $executor = $this->createMock(HttpRequestExecutorInterface::class);
        $executor->expects($this->once())
            ->method('execute')
            ->with($this->callback(function (HttpExchangeRequest $request) {
                $this->assertSame(
                    'https://sapi.synthetic.test/provider/sapi',
                    $request->baseUrlOrNull()->value()
                );
                $this->assertSame('/v1/status', $request->path());

                return true;
            }))
            ->willReturn(new HttpExchangeResult(200, '{}', [], microtime(true)));

        $transport = $this->transport($executor, 'https://sapi.synthetic.test/provider/sapi/');

        $transport->send(HttpExchangeRequestFactory::create(HttpMethod::fromString('GET'), '/v1/status'));
    }

    public function testBackpressureKeyRemainsStableWhenBearerTokenChanges(): void
    {
        $exchange = new HttpExchangeResult(200, '{}', [], microtime(true));
        $keys     = [];
        $executor = $this->createMock(HttpRequestExecutorInterface::class);
        $executor->expects($this->exactly(2))
            ->method('execute')
            ->willReturnCallback(function (HttpExchangeRequest $request) use (&$keys, $exchange): HttpExchangeResult {
                $keys[] = HttpRequestBackpressureKey::forRequest($request);

                return $exchange;
            });

        $transport = $this->transport($executor);
        $transport->send(HttpExchangeRequestFactory::create(
            HttpMethod::fromString('GET'),
            '/v1/first',
            [$this->authorizationHeader('synthetic-token-before-refresh')]
        ));
        $transport->send(HttpExchangeRequestFactory::create(
            HttpMethod::fromString('GET'),
            '/sapi/second',
            [$this->authorizationHeader('synthetic-token-after-refresh')]
        ));

        $this->assertCount(2, $keys);
        $this->assertSame($keys[0], $keys[1]);
    }

    public function testConnectionFailurePropagatesAsHttpTransportException(): void
    {
        $failure  = new ProviderConnectionException('Synthetic connection failure.', 503);
        $executor = $this->createMock(HttpRequestExecutorInterface::class);
        $executor->expects($this->once())->method('execute')->willThrowException($failure);

        try {
            $this->transport($executor)->send(HttpExchangeRequestFactory::create(HttpMethod::fromString('POST'), '/v1/discovery/receives', [], '{}'));
            $this->fail('Connection failures must be reported to the caller.');
        } catch (ProviderApiException $exception) {
            $this->assertSame($failure, $exception);
        }
    }

    private function transport(
        HttpRequestExecutorInterface $executor,
        string $baseUrl = 'https://sapi.synthetic.test'
    ): ProviderTransport {
        $configuration = TestConfiguration::connector(
            TestConfiguration::CLIENT_ID,
            TestConfiguration::CLIENT_SECRET,
            $baseUrl
        );
        return ProviderTransport::forSapiClient($configuration, $executor);
    }

    private function authorizationHeader(string $token): HttpHeaderLine
    {
        return HttpHeaderLine::fromParts(
            HttpHeaderName::authorization(),
            'Bearer ' . $token
        );
    }
}
