<?php

namespace DreaM1\EfakturaConnector\Tests\Unit\Infrastructure\Ubl;

use DOMDocument;
use DreaM1\EfakturaConnector\Infrastructure\Ubl\Reader\UblXPath;
use PHPUnit\Framework\TestCase;

/** Tests UBL XPath expression building and query helpers. */
class UblXPathTest extends TestCase
{
    public function testFirstNodeValueReturnsNullWhenEveryRelativePathIsEmpty(): void
    {
        $document = new DOMDocument();
        $this->assertTrue($document->loadXML('<Invoice><Line><Name> </Name></Line></Invoice>'));
        $line = $document->documentElement->firstChild;

        $this->assertNull((new UblXPath($document))->firstNodeValue($line, 'cbc:Name', 'cbc:ID'));
    }

    public function testQueryNodesSupportsAbsoluteAndContextRelativeExpressions(): void
    {
        $document = new DOMDocument();
        $this->assertTrue($document->loadXML(
            '<Invoice xmlns:cac="urn:cac" xmlns:cbc="urn:cbc">'
                . '<cac:InvoiceLine><cac:AllowanceCharge><cbc:Amount>1</cbc:Amount></cac:AllowanceCharge>'
                . '</cac:InvoiceLine></Invoice>'
        ));
        $xpath = new UblXPath($document);
        $lines = $xpath->queryNodes('//cac:InvoiceLine');

        $this->assertCount(1, $lines);
        $this->assertCount(1, $xpath->queryNodes('cac:AllowanceCharge', $lines[0]));
    }
}
