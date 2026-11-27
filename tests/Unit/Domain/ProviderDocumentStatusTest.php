<?php

namespace DreaM1\EfakturaConnector\Tests\Unit\Domain;

use DreaM1\EfakturaConnector\Domain\EFaktura\ProviderDocumentStatus;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/** Checks supported provider document statuses and outbound-status classification. */
class ProviderDocumentStatusTest extends TestCase
{
    public function testCreatesSupportedStatusAndComparesValues(): void
    {
        $status = ProviderDocumentStatus::fromString(ProviderDocumentStatus::ACCEPTED);

        $this->assertSame(ProviderDocumentStatus::ACCEPTED, $status->value());
        $this->assertTrue($status->equals(ProviderDocumentStatus::fromString(ProviderDocumentStatus::ACCEPTED)));
        $this->assertFalse($status->equals(ProviderDocumentStatus::fromString(ProviderDocumentStatus::REJECTED)));
    }

    public function testClassifiesOutboundStatuses(): void
    {
        $this->assertTrue(ProviderDocumentStatus::fromString(ProviderDocumentStatus::DELIVERY_FAILED)->isOutboundStatus());
        $this->assertFalse(ProviderDocumentStatus::fromString(ProviderDocumentStatus::ACCEPTED)->isOutboundStatus());
    }

    public function testProviderStatusPreservesUnknownValuesForHostClassification(): void
    {
        $known   = ProviderDocumentStatus::fromProvider(ProviderDocumentStatus::ACCEPTED);
        $unknown = ProviderDocumentStatus::fromProvider('FUTURE_STATUS');

        $this->assertTrue($known->isKnown());
        $this->assertFalse($unknown->isKnown());
        $this->assertSame('FUTURE_STATUS', $unknown->value());
        $this->assertFalse($unknown->isOutboundStatus());
    }

    public function testRejectsUnsupportedStatus(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported provider document status.');

        ProviderDocumentStatus::fromString('UNKNOWN');
    }
}
