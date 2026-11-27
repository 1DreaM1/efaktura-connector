<?php

namespace DreaM1\EfakturaConnector\Tests\Unit\Infrastructure\EFaktura\Api\Response;

use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Response\ProviderErrorEnvelopeParser;
use PHPUnit\Framework\TestCase;

/** Tests tolerant provider error parsing at the API response boundary. */
class ProviderErrorEnvelopeParserTest extends TestCase
{
    public function testParsesARecognizedErrorAndReturnsNullForOtherBodies(): void
    {
        $error = ProviderErrorEnvelopeParser::parse('{"error":{"message":"Service unavailable","retryable":true}}');

        $this->assertNotNull($error);
        $this->assertSame('Service unavailable', $error->message());
        $this->assertTrue($error->retryable());
        $this->assertNull($error->category());
        $this->assertNull($error->code());
        $this->assertNull($error->correlationId());
        $this->assertSame([], $error->details());
        $this->assertNull(ProviderErrorEnvelopeParser::parse('{broken'));
        $this->assertNull(ProviderErrorEnvelopeParser::parse('"scalar"'));
        $this->assertNull(ProviderErrorEnvelopeParser::parse('<html>Bad gateway</html>'));
    }
}
