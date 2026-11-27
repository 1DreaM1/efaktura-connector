<?php

namespace DreaM1\EfakturaConnector\Tests\Unit\Domain\Ubl;

use DreaM1\EfakturaConnector\Domain\Ubl\UblDocument;
use DreaM1\EfakturaConnector\Domain\Ubl\UblDocumentContent;
use DreaM1\EfakturaConnector\Domain\Ubl\UblDocumentHeader;
use DreaM1\EfakturaConnector\Domain\Ubl\UblDocumentParty;
use DreaM1\EfakturaConnector\Domain\Ubl\UblDocumentRoot;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/** Tests aggregate invariants for parsed UBL documents. */
class UblDocumentTest extends TestCase
{
    public function testRejectsDifferentHeaderAndContentRoots(): void
    {
        $header  = UblDocumentHeader::fromArray(['documentRoot' => UblDocumentRoot::CREDIT_NOTE]);
        $content = $this->content(UblDocumentRoot::INVOICE);

        $this->expectException(InvalidArgumentException::class);

        new UblDocument($header, $content);
    }

    public function testAcceptsMatchingHeaderAndContentRoots(): void
    {
        $kind    = UblDocumentRoot::CREDIT_NOTE;
        $header  = UblDocumentHeader::fromArray(['documentRoot' => $kind]);
        $content = $this->content($kind);

        $document = new UblDocument($header, $content);

        $this->assertSame($kind, $document->kind());
    }

    private function content(string $kind): UblDocumentContent
    {
        return UblDocumentContent::create(
            $kind,
            UblDocumentParty::fromArray([]),
            UblDocumentParty::fromArray([]),
            [],
            false,
            []
        );
    }
}
