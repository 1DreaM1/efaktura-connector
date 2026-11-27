<?php

namespace DreaM1\EfakturaConnector\Tests\Unit\Domain\Ubl;

use DreaM1\EfakturaConnector\Domain\Ubl\UblDocumentRoot;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/** Checks recognition of Invoice and CreditNote roots and rejection of unsupported names. */
class UblDocumentRootTest extends TestCase
{
    public function testCreatesAndComparesSupportedRoots(): void
    {
        $invoice    = UblDocumentRoot::fromString(UblDocumentRoot::INVOICE);
        $creditNote = UblDocumentRoot::fromString(UblDocumentRoot::CREDIT_NOTE);

        $this->assertSame(UblDocumentRoot::INVOICE, $invoice->value());
        $this->assertTrue($invoice->equals(UblDocumentRoot::fromString(UblDocumentRoot::INVOICE)));
        $this->assertFalse($invoice->equals($creditNote));
        $this->assertFalse($invoice->isCreditNote());
        $this->assertTrue($creditNote->isCreditNote());
    }

    public function testRejectsUnsupportedRoot(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('A UBL Invoice or CreditNote root is required.');

        UblDocumentRoot::fromString('Order');
    }
}
