<?php

namespace DreaM1\EfakturaConnector\Tests\Unit\Core\Documents;

use DreaM1\EfakturaConnector\Core\Documents\OutboundGatewayDocument;
use DreaM1\EfakturaConnector\Core\Xml\XmlPayloadFormat;
use DreaM1\EfakturaConnector\Domain\EFaktura\EFakturaDocumentLimits;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/** Tests Gateway document size validation. */
final class OutboundGatewayDocumentTest extends TestCase
{
    public function testRejectsPayloadAboveTheConfiguredSizeLimit(): void
    {
        $payload = str_repeat('x', EFakturaDocumentLimits::MAX_XML_SIZE_BYTES + 1);

        try {
            new OutboundGatewayDocument(XmlPayloadFormat::SBDH_XML, $payload);
            $this->fail('A Gateway document above the configured XML size limit must be rejected.');
        } catch (InvalidArgumentException $exception) {
            $this->assertSame(
                'Gateway document payload exceeds the maximum allowed size of 10485760 bytes.',
                $exception->getMessage()
            );
        }
    }
}
