<?php

namespace DreaM1\EfakturaConnector\Tests\Unit\Infrastructure\EFaktura\Api\Transport {
    use DreaM1\EfakturaConnector\Core\Configuration\ProviderBaseUrl;
    use DreaM1\EfakturaConnector\Core\Exceptions\ProviderConnectionException;
    use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Transport\Execution\CurlRequestExecutorTestHarness;
    use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Transport\Execution\DefaultHttpRequestExecutorFactory;
    use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Transport\Execution\HttpRequestExecutorOptions;
    use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Transport\Execution\PooledCurlRequestExecutor;
    use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Transport\HttpExchangeRequest;
    use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Http\Protocol\HttpHeaderLine;
    use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Http\Protocol\HttpHeaderName;
    use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Http\Protocol\HttpMethod;
    use DreaM1\EfakturaConnector\Infrastructure\ResourcePath;
    use DreaM1\EfakturaConnector\Tests\Support\HttpExchangeRequestFactory;
    use PHPUnit\Framework\TestCase;

    require_once ResourcePath::join(__DIR__, '../../../../../Support/CurlRequestExecutorTestHarness.php');

    /** Tests pooled cURL request execution and handle reuse behavior. */
    class PooledCurlRequestExecutorTest extends TestCase
    {
        protected function setUp(): void
        {
            CurlRequestExecutorTestHarness::reset();
        }

        public function testReusesOneHandleAcrossRequestsAndResetsRequestOptions(): void
        {
            $executor                                     = new PooledCurlRequestExecutor();
            CurlRequestExecutorTestHarness::$responseBody = 'first response';

            $first = $executor->execute(HttpExchangeRequestFactory::create(
                HttpMethod::fromString('POST'),
                '/v1/send',
                [],
                'first request body',
                [],
                null,
                ProviderBaseUrl::fromString('https://api.synthetic.test')
            ));

            CurlRequestExecutorTestHarness::$responseBody = 'second response';
            $second                                       = $executor->execute(HttpExchangeRequestFactory::create(
                HttpMethod::fromString('GET'),
                '/v1/status',
                [HttpHeaderLine::fromParts(HttpHeaderName::fromString('X-Request'), 'second')],
                null,
                [],
                null,
                ProviderBaseUrl::fromString('https://api.synthetic.test')
            ));

            $this->assertSame('first response', $first->body());
            $this->assertSame('second response', $second->body());
            $this->assertSame(1, CurlRequestExecutorTestHarness::$initCount);
            $this->assertSame(2, CurlRequestExecutorTestHarness::$resetCount);
            $this->assertSame(0, CurlRequestExecutorTestHarness::$closeCount);
            $this->assertSame(
                CurlRequestExecutorTestHarness::$handleIds[0],
                CurlRequestExecutorTestHarness::$handleIds[1]
            );
            $this->assertSame(
                'https://api.synthetic.test/v1/status',
                CurlRequestExecutorTestHarness::$optionHistory[1][CURLOPT_URL]
            );
            $this->assertArrayNotHasKey(CURLOPT_POSTFIELDS, CurlRequestExecutorTestHarness::$optionHistory[1]);

            $executor->close();
            $executor->close();
            $this->assertSame(1, CurlRequestExecutorTestHarness::$closeCount);
        }

        public function testDiscardsFailedHandleAndCreatesAnotherForTheNextRequest(): void
        {
            $executor                                   = new PooledCurlRequestExecutor();
            CurlRequestExecutorTestHarness::$execResult = false;
            CurlRequestExecutorTestHarness::$error      = 'synthetic timeout';

            try {
                $executor->execute($this->request());
                $this->fail('A cURL transfer failure must be reported.');
            } catch (ProviderConnectionException $exception) {
                $this->assertSame('synthetic timeout', $exception->getMessage());
            }

            $this->assertSame(1, CurlRequestExecutorTestHarness::$closeCount);
            CurlRequestExecutorTestHarness::$execResult = 'success';
            $executor->execute($this->request());

            $this->assertSame(2, CurlRequestExecutorTestHarness::$initCount);
            $this->assertSame(2, CurlRequestExecutorTestHarness::$resetCount);
            $executor->close();
            $this->assertSame(2, CurlRequestExecutorTestHarness::$closeCount);
        }

        public function testCanBeInstalledAsTheFactoryBaseExecutor(): void
        {
            $executor = new PooledCurlRequestExecutor();
            $stack    = DefaultHttpRequestExecutorFactory::create(
                HttpRequestExecutorOptions::defaults()->withBaseExecutor($executor)
            );

            $stack->execute($this->request());
            $stack->execute($this->request());

            $this->assertSame(1, CurlRequestExecutorTestHarness::$initCount);
            $this->assertSame(2, CurlRequestExecutorTestHarness::$resetCount);

            $executor->close();
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
                ProviderBaseUrl::fromString('https://api.synthetic.test')
            );
        }
    }
}
