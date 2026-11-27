<?php

namespace DreaM1\EfakturaConnector\Tests\Unit\Infrastructure\EFaktura\Api\Transport;

use DreaM1\EfakturaConnector\Core\Configuration\ProviderBaseUrl;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Transport\HttpExchangeRequest;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Transport\HttpExchangeResult;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Http\Protocol\HttpHeaderLine;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Http\Protocol\HttpHeaderName;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Http\Protocol\HttpMethod;
use DreaM1\EfakturaConnector\Tests\Support\HttpExchangeRequestFactory;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/** Tests HTTP transport request and response value objects. */
class TransportValueObjectsTest extends TestCase
{
    public function testParsesHeaderNameWithoutCaseAndSurroundingWhitespace(): void
    {
        $header = HttpHeaderLine::parse("  X-Request-ID \t:  synthetic-id  ");

        $this->assertSame('x-request-id', $header->name());
        $this->assertSame('synthetic-id', $header->value());
    }

    public function testTypedHeaderHasOneCanonicalSerialization(): void
    {
        $header = HttpHeaderLine::fromParts(HttpHeaderName::fromString('X-Request-ID'), 'synthetic-id');

        $this->assertSame('X-Request-ID: synthetic-id', $header->toString());
    }

    public function testNamedFactoriesCreateCanonicalHeaderNames(): void
    {
        $headerNames = [
            HttpHeaderName::accept(),
            HttpHeaderName::authorization(),
            HttpHeaderName::contentType(),
            HttpHeaderName::idempotencyKey(),
            HttpHeaderName::peppolParticipantId(),
            HttpHeaderName::retryAfter(),
            HttpHeaderName::xCorrelationId(),
            HttpHeaderName::xRequestId(),
        ];

        $this->assertSame(
            [
                HttpHeaderName::ACCEPT,
                HttpHeaderName::AUTHORIZATION,
                HttpHeaderName::CONTENT_TYPE,
                HttpHeaderName::IDEMPOTENCY_KEY,
                HttpHeaderName::PEPPOL_PARTICIPANT_ID,
                HttpHeaderName::RETRY_AFTER,
                HttpHeaderName::X_CORRELATION_ID,
                HttpHeaderName::X_REQUEST_ID,
            ],
            array_map(
                static function (HttpHeaderName $headerName): string {
                    return $headerName->value();
                },
                $headerNames
            )
        );
    }

    /**
     * Ignores lines that do not represent valid HTTP response headers.
     *
     * @param string $line Header line supplied to the response parser.
     * @dataProvider malformedHeaders
     */
    public function testIgnoresNonHeaderLines($line): void
    {
        $this->assertNull(HttpHeaderLine::parse($line));
    }

    /**
     * Provides malformed header lines that must be ignored by response parsing.
     *
     * @return array<array-key, array<array-key, mixed>> Malformed header cases.
     */
    public function malformedHeaders(): array
    {
        return [
            'status line'       => ['HTTP/1.1 200 OK'],
            'empty line'        => ["\r\n"],
            'header terminator' => ["X-Request-ID: synthetic-id\r\n"],
            'carriage return'   => ["X-Request-ID: synthetic\rinjected"],
            'line feed'         => ["X-Request-ID: synthetic\ninjected"],
            'invalid control'   => ["X-Request-ID: synthetic\x01value"],
            'header injection'  => ["X-Request-ID: synthetic-id\r\nAuthorization: Bearer injected"],
            'non-string value'  => [42],
        ];
    }

    public function testDetectsAnEmptyNoContentResponse(): void
    {
        $response = new HttpExchangeResult(204, '', ['x-request-id' => 'synthetic'], 12.5);
        $this->assertTrue($response->isWithoutContent());
        $this->assertSame(204, $response->rawStatusCode());
        $this->assertSame(204, $response->httpStatusCode()->value());
        $this->assertTrue($response->httpStatusCode()->isNoContent());
        $this->assertSame('', $response->body());
        $this->assertSame(['x-request-id' => 'synthetic'], $response->headers());
        $this->assertSame(12.5, $response->startedAt());
        $this->assertFalse((new HttpExchangeResult(204, 'unexpected', [], 0.0))->isWithoutContent());
        $this->assertFalse((new HttpExchangeResult(200, '', [], 0.0))->isWithoutContent());
    }

    public function testInvalidStatusIsRetainedRawAndClassifiedAsServerError(): void
    {
        $response = new HttpExchangeResult(600, 'invalid status', [], 0.0);

        $this->assertSame(600, $response->rawStatusCode());
        $this->assertNull($response->httpStatusCode());
        $this->assertFalse($response->isSuccessful());
        $this->assertTrue($response->isServerError());
        $this->assertFalse($response->isTooManyRequests());
    }

    public function testTransportRequestRetainsItsConfiguredBaseUrlAndRelativePath(): void
    {
        $baseUrl = ProviderBaseUrl::fromString('https://provider.synthetic.test/sapi/');
        $request = (HttpExchangeRequestFactory::create(HttpMethod::fromString('GET'), '/v1/test'))
            ->withBaseUrl($baseUrl);

        $this->assertSame($baseUrl, $request->baseUrlOrNull());
        $this->assertSame('/v1/test', $request->path());
    }

    public function testAcceptHeaderRejectsCarriageReturnAndLineFeedInjection(): void
    {
        $this->expectException(InvalidArgumentException::class);

        HttpExchangeRequestFactory::create(
            HttpMethod::fromString('GET'),
            '/v1/test',
            [],
            null,
            [],
            "application/json\r\nX-Injected: yes"
        );
    }
}
