<?php

namespace DreaM1\EfakturaConnector\Tests\Unit\Infrastructure\Ubl;

use DOMDocument;
use DreaM1\EfakturaConnector\Core\Ubl\UblNamespaces;
use DreaM1\EfakturaConnector\Infrastructure\Ubl\UblDocumentType;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/** Tests UBL document root and type mapping helpers. */
class UblDocumentTypeTest extends TestCase
{
    public function testInvoiceAndCreditNoteShareOneTypeDefinition(): void
    {
        $invoice    = UblDocumentType::fromKind('Invoice');
        $creditNote = UblDocumentType::fromKind('CreditNote');

        $this->assertSame('Invoice', $invoice->rootElement());
        $this->assertSame('InvoiceTypeCode', $invoice->typeCodeElement());
        $this->assertSame('InvoiceLine', $invoice->lineElement());
        $this->assertSame('InvoicedQuantity', $invoice->quantityElement());
        $this->assertSame('urn:oasis:names:specification:ubl:schema:xsd:Invoice-2', $invoice->documentNamespace());
        $this->assertFalse($invoice->isCreditNote());

        $this->assertSame('CreditNote', $creditNote->rootElement());
        $this->assertSame('CreditNoteTypeCode', $creditNote->typeCodeElement());
        $this->assertSame('CreditNoteLine', $creditNote->lineElement());
        $this->assertSame('CreditedQuantity', $creditNote->quantityElement());
        $this->assertSame('urn:oasis:names:specification:ubl:schema:xsd:CreditNote-2', $creditNote->documentNamespace());
        $this->assertTrue($creditNote->isCreditNote());
    }

    public function testRootResolutionRequiresSupportedKindAndNamespace(): void
    {
        $document = new DOMDocument();
        $document->loadXML('<CreditNote xmlns="urn:oasis:names:specification:ubl:schema:xsd:CreditNote-2"/>');

        $this->assertSame('CreditNote', UblDocumentType::fromRoot($document->documentElement)->rootElement());

        $document->loadXML('<CreditNote xmlns="urn:example:invalid"/>');
        $this->assertNull(UblDocumentType::fromRoot($document->documentElement));
        $this->assertNull(UblDocumentType::fromRoot(null));
    }

    public function testDocumentAndInfrastructureUseTheSameSupportedRootRule(): void
    {
        $cases = [
            [
                '<Invoice xmlns="urn:oasis:names:specification:ubl:schema:xsd:Invoice-2"/>',
                'Invoice',
            ],
            [
                '<CreditNote xmlns="urn:oasis:names:specification:ubl:schema:xsd:CreditNote-2"/>',
                'CreditNote',
            ],
            [
                '<Invoice xmlns="urn:oasis:names:specification:ubl:schema:xsd:CreditNote-2"/>',
                null,
            ],
            [
                '<Invoice xmlns="urn:oasis:names:specification:ubl:schema:xsd:Invoice-3"/>',
                null,
            ],
            [
                '<Order xmlns="urn:oasis:names:specification:ubl:schema:xsd:Order-2"/>',
                null,
            ],
        ];

        foreach ($cases as [$xml, $expectedKind]) {
            $document = new DOMDocument();
            $this->assertTrue($document->loadXML($xml));
            $root        = $document->documentElement;
            $isSupported = UblNamespaces::isSupportedRoot($root);
            $type        = UblDocumentType::fromRoot($root);

            $this->assertSame($expectedKind !== null, $isSupported);
            $this->assertSame($isSupported, $type !== null);

            if ($type !== null) {
                $this->assertSame($expectedKind, $type->rootElement());
                $this->assertSame(
                    UblNamespaces::documentNamespaceFor($expectedKind),
                    $type->documentNamespace()
                );
            }
        }
    }

    public function testKindResolutionRejectsOtherKinds(): void
    {
        $this->expectException(InvalidArgumentException::class);
        UblDocumentType::fromKind('Order');
    }
}
