<?php

namespace DreaM1\EfakturaConnector\Tests\Unit\Infrastructure\EFaktura\Api;

use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Authentication\PdsAuthenticatedRequestSender;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Transport\HttpExchangeRequest;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Transport\HttpExchangeResult;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Transport\HttpTransportInterface;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Http\Protocol\HttpHeaderLine;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Http\Protocol\HttpHeaderName;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Http\Protocol\HttpMethod;
use DreaM1\EfakturaConnector\Tests\Support\HttpExchangeRequestFactory;
use DreaM1\EfakturaConnector\Tests\Support\RecordingTransport;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/** Tests PDS signature validation and authentication-header isolation. */
final class PdsAuthenticatedRequestSenderTest extends TestCase
{
    /**
     * @dataProvider invalidSignatureHeaders
     * @param HttpHeaderLine[] $headers
     */
    public function testRequiresExactlyOneNonEmptyPdsSignature(array $headers): void
    {
        $transport = $this->createMock(HttpTransportInterface::class);
        $transport->expects($this->never())->method('send');

        $this->expectException(InvalidArgumentException::class);

        (new PdsAuthenticatedRequestSender($transport))->send($this->request($headers));
    }

    public function testRemovesOtherAuthenticationHeadersAndPreservesThePdsSignature(): void
    {
        $transport = RecordingTransport::returning(new HttpExchangeResult(200, '{}', [], microtime(true)));
        $request   = $this->request([
            HttpHeaderLine::fromParts(HttpHeaderName::authorization(), 'Bearer caller-token'),
            HttpHeaderLine::fromParts(HttpHeaderName::integratorToken(), 'caller-integrator-token'),
            HttpHeaderLine::fromParts(HttpHeaderName::pdsSecret(), 'synthetic-signature'),
            HttpHeaderLine::fromParts(HttpHeaderName::xRequestId(), 'synthetic-request-id'),
        ]);

        (new PdsAuthenticatedRequestSender($transport))->send($request);

        $headers = array_map(static function (HttpHeaderLine $header): string {
            return $header->toString();
        }, $transport->lastRequest()->headers()->all());

        $this->assertSame([
            'X-PDS-Secret: synthetic-signature',
            'X-Request-Id: synthetic-request-id',
        ], $headers);
        $this->assertSame([
            'Authorization: Bearer caller-token',
            'X-Integrator-Token: caller-integrator-token',
            'X-PDS-Secret: synthetic-signature',
            'X-Request-Id: synthetic-request-id',
        ], array_map(static function (HttpHeaderLine $header): string {
            return $header->toString();
        }, $request->headers()->all()));
    }

    /** @return array<string, array{0: HttpHeaderLine[]}> */
    public static function invalidSignatureHeaders(): array
    {
        return [
            'missing'         => [[]],
            'empty'           => [[HttpHeaderLine::fromParts(HttpHeaderName::pdsSecret(), '')]],
            'whitespace only' => [[HttpHeaderLine::fromParts(HttpHeaderName::pdsSecret(), " \t")]],
            'duplicate'       => [[
                HttpHeaderLine::fromParts(HttpHeaderName::pdsSecret(), 'first-signature'),
                HttpHeaderLine::fromParts(HttpHeaderName::pdsSecret(), 'second-signature'),
            ]],
            'case-insensitive duplicate' => [[
                HttpHeaderLine::fromParts(HttpHeaderName::pdsSecret(), 'first-signature'),
                HttpHeaderLine::fromParts(HttpHeaderName::fromString('x-pds-secret'), 'second-signature'),
            ]],
        ];
    }

    /** @param HttpHeaderLine[] $headers */
    private function request(array $headers): HttpExchangeRequest
    {
        return HttpExchangeRequestFactory::create(HttpMethod::post(), '/v1/integrators/integrator-1/pds/subjects', $headers, '[]');
    }
}
