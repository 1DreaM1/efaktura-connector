<?php

namespace DreaM1\EfakturaConnector\Tests\Unit\Infrastructure\EFaktura\Api;

use DreaM1\EfakturaConnector\Core\IntegratorApi\PdsSigningSecret;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\ApiRequest;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Authentication\PdsRequestSigner;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Http\Protocol\HttpHeaderName;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/** Tests PDS request signing against the encoded JSON body. */
final class PdsRequestSignerTest extends TestCase
{
    public function testSignsTheEncodedBodyAndReturnsARequestWithThePdsHeader(): void
    {
        $request = ApiRequest::post('/v1/integrators/integrator-1/pds/subjects')
            ->withJsonBody([['legalName' => 'Žilina & Sons']]);
        $secret = PdsSigningSecret::fromString('synthetic-pds-secret');

        $signedRequest = PdsRequestSigner::sign($request, $secret);
        $signatures    = $signedRequest->headers()->allNamed(HttpHeaderName::pdsSecret());

        $this->assertSame(1, count($signatures));
        $this->assertSame(
            hash_hmac('sha512', (string) $request->body(), $secret->reveal()),
            $signatures[0]->value()
        );
        $this->assertSame($request->body(), $signedRequest->body());
        $this->assertSame([], $request->headers()->all());
    }

    public function testRejectsARequestWithoutABody(): void
    {
        $this->expectException(InvalidArgumentException::class);

        PdsRequestSigner::sign(
            ApiRequest::post('/v1/integrators/integrator-1/pds/subjects'),
            PdsSigningSecret::fromString('synthetic-pds-secret')
        );
    }
}
