<?php

namespace DreaM1\EfakturaConnector\Tests\Unit\Infrastructure\EFaktura\Connection;

use DreaM1\EfakturaConnector\Core\Connection\Health\TokenStatus;
use DreaM1\EfakturaConnector\Core\Exceptions\ProviderApiException;
use DreaM1\EfakturaConnector\Core\Exceptions\ProviderErrorResponseException;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Endpoints\Sapi\GetTokenStatusEndpoint;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Response\Sapi\TokenStatusFactory;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Connection\SapiTokenHealthProvider;
use DreaM1\EfakturaConnector\Support\Timestamp;
use DreaM1\EfakturaConnector\Tests\Support\ApiFixtures;
use PHPUnit\Framework\TestCase;

/** Tests SAPI token health status mapping. */
class SapiTokenHealthProviderTest extends TestCase
{
    public function testMapsFullTokenStatusResponseFromSapi(): void
    {
        $response = TokenStatusFactory::fromArray(ApiFixtures::tokenStatus(true));
        $api      = $this->createMock(GetTokenStatusEndpoint::class);
        $api->expects($this->once())
            ->method('send')
            ->willReturn($response);

        $result = (new SapiTokenHealthProvider($api))->getTokenStatus();

        $this->assertInstanceOf(TokenStatus::class, $result);
        $this->assertTrue($result->valid());
        $this->assertSame('access', $result->tokenType());
        $this->assertSame('synthetic-client', $result->clientId()->value());
        $this->assertInstanceOf(Timestamp::class, $result->issuedAt());
        $this->assertSame('2026-11-18T20:45:26Z', $result->issuedAt()->value());
        $this->assertSame('2026-11-18T21:00:26Z', $result->expiresAt()->value());
        $this->assertSame(900, $result->expiresInSeconds());
        $this->assertFalse($result->shouldRefresh());
        $this->assertSame('2026-11-18T20:57:26Z', $result->refreshRecommendedAt()->value());
        $this->assertSame('2026-11-18T20:45:26Z', $result->lifetime()->issuedAt()->value());
        $this->assertSame('2026-11-18T21:00:26Z', $result->lifetime()->expiresAt()->value());
        $this->assertSame(900, $result->lifetime()->expiresInSeconds());
        $this->assertFalse($result->refreshRecommendation()->shouldRefresh());
        $this->assertSame(
            '2026-11-18T20:57:26Z',
            $result->refreshRecommendation()->refreshRecommendedAt()->value()
        );
    }

    public function testReturnsTokenStatusFromSapi(): void
    {
        $api = $this->createMock(GetTokenStatusEndpoint::class);
        $api->expects($this->once())
            ->method('send')
            ->willReturn(TokenStatusFactory::fromArray(ApiFixtures::tokenStatus(true)));

        $this->assertTrue((new SapiTokenHealthProvider($api))->isTokenValid());
    }

    public function testRejectsMalformedTokenStatus(): void
    {
        $api = $this->createMock(GetTokenStatusEndpoint::class);
        $api->method('send')->willReturn(TokenStatusFactory::fromArray(['token_type' => 'access']));

        $this->expectException(ProviderApiException::class);
        $this->expectExceptionMessage('SAPI returned an invalid token status.');

        (new SapiTokenHealthProvider($api))->isTokenValid();
    }

    public function testSapiFailuresOtherThanUnauthorizedPropagateUnchanged(): void
    {
        // A 401 is reported as an invalid token; see TokenStatusUnauthorizedTest.
        foreach ([403, 423, 404, 503] as $statusCode) {
            $failure = new ProviderErrorResponseException($statusCode);
            $api     = $this->createMock(GetTokenStatusEndpoint::class);
            $api->method('send')->willThrowException($failure);

            try {
                (new SapiTokenHealthProvider($api))->isTokenValid();
                $this->fail('SAPI errors must reach the caller.');
            } catch (ProviderApiException $exception) {
                $this->assertSame($failure, $exception);
            }
        }
    }
}
