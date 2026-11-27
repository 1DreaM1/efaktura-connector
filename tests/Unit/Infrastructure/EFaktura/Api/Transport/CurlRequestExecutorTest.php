<?php

namespace DreaM1\EfakturaConnector\Tests\Unit\Infrastructure\EFaktura\Api\Transport {
    use DreaM1\EfakturaConnector\Core\Configuration\HttpResponseBodyLimit;
    use DreaM1\EfakturaConnector\Core\Configuration\ProviderBaseUrl;
    use DreaM1\EfakturaConnector\Core\Exceptions\ProviderConnectionException;
    use DreaM1\EfakturaConnector\Core\Exceptions\ProviderResponseTooLargeException;
    use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Transport\Execution\CurlRequestExecutor;
    use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Transport\Execution\CurlRequestExecutorTestHarness;
    use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Transport\HttpExchangeRequest;
    use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Http\Protocol\HttpHeaderLine;
    use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Http\Protocol\HttpHeaderName;
    use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Http\Protocol\HttpMethod;
    use DreaM1\EfakturaConnector\Infrastructure\ResourcePath;
    use DreaM1\EfakturaConnector\Tests\Support\HttpExchangeRequestFactory;
    use PHPUnit\Framework\TestCase;

    require_once ResourcePath::join(__DIR__, '../../../../../Support/CurlRequestExecutorTestHarness.php');

    /** Tests cURL request execution and transport failure handling. */
    class CurlRequestExecutorTest extends TestCase
    {
        protected function setUp(): void
        {
            CurlRequestExecutorTestHarness::reset();
        }

        public function testSendsSecureRequestAndCollectsTheResponse(): void
        {
            CurlRequestExecutorTestHarness::$responseBody    = '{"valid":true}';
            CurlRequestExecutorTestHarness::$responseHeaders = [
                'HTTP/1.1 200 OK',
                'Content-Type: application/json',
                'X-Request-Id: synthetic-request',
                'Set-Cookie: ignored',
            ];

            $result = (new CurlRequestExecutor())->execute(HttpExchangeRequestFactory::create(
                HttpMethod::fromString('POST'),
                '/v1/validate',
                [HttpHeaderLine::fromParts(HttpHeaderName::fromString('X-Test'), 'synthetic')],
                '{"input":true}',
                [],
                null,
                ProviderBaseUrl::fromString('https://api.synthetic.test/provider/sapi/')
            ));

            $options = CurlRequestExecutorTestHarness::$options;
            $this->assertSame(
                'https://api.synthetic.test/provider/sapi/v1/validate',
                CurlRequestExecutorTestHarness::$url
            );
            $this->assertSame('POST', $options[CURLOPT_CUSTOMREQUEST]);
            $this->assertSame([
                'Accept: application/json',
                'Content-Type: application/json',
                'X-Test: synthetic',
                'Expect:',
            ], $options[CURLOPT_HTTPHEADER]);
            $this->assertSame('{"input":true}', $options[CURLOPT_POSTFIELDS]);
            $this->assertFalse($options[CURLOPT_FOLLOWLOCATION]);
            $this->assertTrue($options[CURLOPT_SSL_VERIFYPEER]);
            $this->assertSame(2, $options[CURLOPT_SSL_VERIFYHOST]);
            $this->assertSame(10, $options[CURLOPT_CONNECTTIMEOUT]);
            $this->assertSame(45, $options[CURLOPT_TIMEOUT]);
            $this->assertSame('{"valid":true}', $result->body());
            $this->assertSame(200, $result->rawStatusCode());
            $this->assertSame([
                'content-type' => 'application/json',
                'x-request-id' => 'synthetic-request',
            ], $result->headers());
            $this->assertTrue(CurlRequestExecutorTestHarness::$closed);
        }

        public function testRequestsWithoutBodyKeepOnlyTheirOwnHeaders(): void
        {
            (new CurlRequestExecutor())->execute(HttpExchangeRequestFactory::create(
                HttpMethod::fromString('GET'),
                '/v1/participants/synthetic',
                [],
                null,
                [],
                null,
                ProviderBaseUrl::fromString('https://api.synthetic.test')
            ));

            $this->assertSame(
                ['Accept: application/json'],
                CurlRequestExecutorTestHarness::$options[CURLOPT_HTTPHEADER]
            );
            $this->assertArrayNotHasKey(CURLOPT_POSTFIELDS, CurlRequestExecutorTestHarness::$options);
        }

        public function testReportsCurlExecutionFailuresWithStatusAndResponseHeaders(): void
        {
            CurlRequestExecutorTestHarness::$execResult      = false;
            CurlRequestExecutorTestHarness::$error           = 'synthetic timeout';
            CurlRequestExecutorTestHarness::$status          = 503;
            CurlRequestExecutorTestHarness::$responseHeaders = ['Retry-After: 2'];

            try {
                (new CurlRequestExecutor())->execute($this->getRequest());
                $this->fail('Expected the cURL failure to be translated.');
            } catch (ProviderConnectionException $exception) {
                $this->assertSame('synthetic timeout', $exception->getMessage());
                $this->assertSame(503, $exception->rawStatusCode());
                $this->assertSame(['retry-after' => '2'], $exception->responseHeaders());
                $this->assertTrue(CurlRequestExecutorTestHarness::$closed);
            }
        }

        public function testStopsReadingResponseWhenConfiguredLimitIsExceeded(): void
        {
            CurlRequestExecutorTestHarness::$responseBody    = '123456';
            CurlRequestExecutorTestHarness::$responseHeaders = ['Content-Type: application/json'];
            CurlRequestExecutorTestHarness::$status          = 200;

            try {
                (new CurlRequestExecutor(10, 45, HttpResponseBodyLimit::fromBytes(5)))->execute($this->getRequest());
                $this->fail('Expected an oversized response to be rejected.');
            } catch (ProviderResponseTooLargeException $exception) {
                $this->assertSame(200, $exception->rawStatusCode());
                $this->assertSame(5, $exception->responseBodyLimit()->bytes());
                $this->assertSame(['content-type' => 'application/json'], $exception->responseHeaders());
                $this->assertStringContainsString('5 bytes', $exception->getMessage());
                $this->assertTrue(CurlRequestExecutorTestHarness::$closed);
            }
        }

        public function testReportsInitializationAndConfigurationFailures(): void
        {
            CurlRequestExecutorTestHarness::$initFails = true;
            try {
                (new CurlRequestExecutor())->execute($this->getRequest());
                $this->fail('Expected cURL initialization failure.');
            } catch (ProviderConnectionException $exception) {
                $this->assertSame('Failed to initialize cURL.', $exception->getMessage());
                $this->assertFalse($exception->requestMayHaveReachedProvider());
                $this->assertFalse(CurlRequestExecutorTestHarness::$closed);
            }

            CurlRequestExecutorTestHarness::reset();
            CurlRequestExecutorTestHarness::$configurationSucceeds = false;
            CurlRequestExecutorTestHarness::$error                 = 'synthetic option failure';
            try {
                (new CurlRequestExecutor())->execute($this->getRequest());
                $this->fail('Expected cURL configuration failure.');
            } catch (ProviderConnectionException $exception) {
                $this->assertSame('synthetic option failure', $exception->getMessage());
                $this->assertFalse($exception->requestMayHaveReachedProvider());
                $this->assertTrue(CurlRequestExecutorTestHarness::$closed);
            }
        }

        public function testUsesFallbackMessagesWhenCurlProvidesNoErrorText(): void
        {
            CurlRequestExecutorTestHarness::$configurationSucceeds = false;

            try {
                (new CurlRequestExecutor())->execute($this->getRequest());
                $this->fail('A cURL configuration failure must be reported.');
            } catch (ProviderConnectionException $exception) {
                $this->assertSame('Failed to configure cURL request.', $exception->getMessage());
                $this->assertTrue(CurlRequestExecutorTestHarness::$closed);
            }

            CurlRequestExecutorTestHarness::reset();
            CurlRequestExecutorTestHarness::$execResult = false;
            CurlRequestExecutorTestHarness::$status     = 0;

            try {
                (new CurlRequestExecutor())->execute($this->getRequest());
                $this->fail('An empty cURL failure must be reported.');
            } catch (ProviderConnectionException $exception) {
                $this->assertSame('cURL request failed without an error message.', $exception->getMessage());
                $this->assertNull($exception->rawStatusCode());
                $this->assertTrue(CurlRequestExecutorTestHarness::$closed);
            }
        }

        private function getRequest(): HttpExchangeRequest
        {
            return HttpExchangeRequestFactory::create(
                HttpMethod::fromString('GET'),
                '/v1/test',
                [],
                null,
                [],
                null,
                ProviderBaseUrl::fromString('https://api.synthetic.test')
            );
        }
    }
}
