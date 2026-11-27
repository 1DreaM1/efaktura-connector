<?php

namespace DreaM1\EfakturaConnector\Tests\Unit\Infrastructure\EFaktura\Api\Transport;

use DreaM1\EfakturaConnector\Core\Configuration\ProviderBaseUrl;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Transport\HttpExchangeRequest;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Http\HttpRequestContext;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Http\HttpRequestTarget;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Http\Protocol\HttpHeaders;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Http\Protocol\HttpMethod;
use DreaM1\EfakturaConnector\Support\Exceptions\InvalidInputException;
use PHPUnit\Framework\TestCase;

/** The provider base URL and backpressure scope are attached after construction and never mutate the original. */
final class HttpExchangeRequestBindingTest extends TestCase
{
    public function testNewRequestIsNotBoundToAProvider(): void
    {
        $request = $this->request();

        $this->assertNull($request->baseUrlOrNull());
        $this->assertNull($request->backpressureScopeKey());
    }

    public function testBindingReturnsACopyAndKeepsTheOriginalUnbound(): void
    {
        $original = $this->request();
        $baseUrl  = ProviderBaseUrl::fromString('https://provider.synthetic.test/sapi/');

        $bound = $original->withBaseUrl($baseUrl)->withBackpressureScopeKey('client-1');

        $this->assertNotSame($original, $bound);
        $this->assertNull($original->baseUrlOrNull());
        $this->assertNull($original->backpressureScopeKey());
        $this->assertSame($baseUrl, $bound->baseUrlOrNull());
        $this->assertSame('client-1', $bound->backpressureScopeKey());
        $this->assertSame('/v1/test', $bound->path());
    }

    public function testBindingOrderDoesNotMatter(): void
    {
        $baseUrl = ProviderBaseUrl::fromString('https://provider.synthetic.test/sapi/');

        $first  = $this->request()->withBaseUrl($baseUrl)->withBackpressureScopeKey('client-1');
        $second = $this->request()->withBackpressureScopeKey('client-1')->withBaseUrl($baseUrl);

        $this->assertSame($first->baseUrlOrNull(), $second->baseUrlOrNull());
        $this->assertSame($first->backpressureScopeKey(), $second->backpressureScopeKey());
    }

    public function testBindingKeepsRetrySafetyAndAcceptHeader(): void
    {
        $post  = $this->request('POST');
        $bound = $post->withBackpressureScopeKey('client-1');

        $this->assertFalse($bound->isRetrySafe());
        $this->assertSame($post->acceptHeader(), $bound->acceptHeader());
        $this->assertTrue($this->request('GET')->withBackpressureScopeKey('client-1')->isRetrySafe());
    }

    public function testBlankBackpressureScopeIsRejected(): void
    {
        $this->expectException(InvalidInputException::class);

        $this->request()->withBackpressureScopeKey('  ');
    }
    private function request(string $method = 'GET'): HttpExchangeRequest
    {
        return new HttpExchangeRequest(
            HttpMethod::fromString($method),
            HttpRequestTarget::fromResolvedTarget('/v1/test'),
            HttpHeaders::fromLines([]),
            null,
            HttpRequestContext::empty()
        );
    }
}
