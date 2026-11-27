<?php

namespace DreaM1\EfakturaConnector\Tests\Unit\Infrastructure\Ubl;

use DOMDocument;
use DOMElement;
use DreaM1\EfakturaConnector\Domain\Ubl\Draft\UblDecimal;
use DreaM1\EfakturaConnector\Infrastructure\Ubl\Writer\UblElementWriter;
use DreaM1\EfakturaConnector\Support\Exceptions\InvalidInputException;
use DreaM1\EfakturaConnector\Support\Xml\SecureXmlDocumentParser;
use PHPUnit\Framework\TestCase;

/** Checks that element and attribute text is escaped or rejected, never truncated or malformed. */
class UblElementWriterTest extends TestCase
{
    public function testEscapesMarkupAndKeepsTheDocumentWellFormed(): void
    {
        [$document, $root] = $this->root();

        UblElementWriter::appendCbc($root, 'Note', 'A & B <C> "D"');
        UblElementWriter::appendIdentifier($root, 'ID', 'X1', 'a&b');

        $xml = (string) $document->saveXML();

        $this->assertStringContainsString('A &amp; B &lt;C&gt; "D"', $xml);
        $this->assertNotNull(SecureXmlDocumentParser::parse($xml));
    }

    /**
     * @dataProvider invalidText
     */
    public function testRejectsInvalidElementText(string $value): void
    {
        [, $root] = $this->root();

        $this->expectException(InvalidInputException::class);
        $this->expectExceptionMessage('UBL element Note');

        UblElementWriter::appendCbc($root, 'Note', $value);
    }

    /**
     * @dataProvider invalidText
     */
    public function testRejectsInvalidAttributeValues(string $value): void
    {
        [, $root] = $this->root();
        $element  = UblElementWriter::appendCbc($root, 'ID', 'X1');

        $this->expectException(InvalidInputException::class);
        $this->expectExceptionMessage('UBL attribute schemeID');

        UblElementWriter::setAttributeIfPresent($element, 'schemeID', $value);
    }

    public function testRejectsInvalidCurrencyAttributeAndAmountText(): void
    {
        [, $root] = $this->root();

        try {
            UblElementWriter::appendAmount($root, 'TaxAmount', '1.00', "E\x00R");
            $this->fail('A currency with a NUL byte must be rejected.');
        } catch (InvalidInputException $exception) {
            $this->assertStringContainsString('UBL attribute currencyID', $exception->getMessage());
        }

        $this->expectException(InvalidInputException::class);
        UblElementWriter::appendDecimalAmount($root, 'TaxAmount', UblDecimal::fromString('1.00'), "EU\x0BR");
    }

    public function testRejectedValueLeavesNoPartialElementBehind(): void
    {
        [, $root] = $this->root();

        try {
            UblElementWriter::appendCbc($root, 'Note', "before\x00after");
            $this->fail('A NUL byte must be rejected.');
        } catch (InvalidInputException $exception) {
            $this->assertSame(0, $root->childNodes->length);
        }
    }

    /** @return array<string, array{string}> */
    public static function invalidText(): array
    {
        return [
            'NUL truncates silently in DOM' => ["nul\x00x"],
            'vertical tab'                  => ["ctl\x0Bx"],
            'malformed UTF-8'               => ["bad\xC3\x28utf8"],
            'noncharacter FFFE'             => ["x\u{FFFE}"],
        ];
    }

    /** @return array{0: DOMDocument, 1: DOMElement} */
    private function root(): array
    {
        $document = new DOMDocument('1.0', 'UTF-8');
        $root     = $document->createElement('Root');
        $document->appendChild($root);

        return [$document, $root];
    }
}
