<?php

namespace DreaM1\EfakturaConnector\Tests\Unit\Infrastructure\Ubl;

use DreaM1\EfakturaConnector\Core\Ubl\Exceptions\InvalidXmlException;
use DreaM1\EfakturaConnector\Core\Ubl\Exceptions\UnsupportedDocumentTypeException;
use DreaM1\EfakturaConnector\Core\Ubl\UblXmlDocument;
use DreaM1\EfakturaConnector\Domain\EFaktura\EFakturaDocumentLimits;
use DreaM1\EfakturaConnector\Infrastructure\Ubl\Reader\UblXmlDocumentLoader;
use DreaM1\EfakturaConnector\Support\Xml\SecureXmlDocumentParser;
use DreaM1\EfakturaConnector\Support\Xml\XmlParseException;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/** Tests secure loading of UBL XML documents. */
class UblXmlDocumentLoaderTest extends TestCase
{
    public function testParsesSupportedInvoiceAndCreditNoteDocuments(): void
    {
        $loader = new UblXmlDocumentLoader();

        $invoice    = $loader->parse($this->documentXml('Invoice'));
        $creditNote = $loader->parse($this->documentXml('CreditNote'));

        $this->assertSame('Invoice', $invoice->kind());
        $this->assertSame('CreditNote', $creditNote->kind());
    }

    public function testParseReturnsTypedDocumentWithOriginalXmlAndKind(): void
    {
        $xml      = $this->documentXml('Invoice');
        $document = (new UblXmlDocumentLoader())->parse($xml);

        $this->assertSame($xml, $document->xml());
        $this->assertSame('Invoice', $document->kind());
        $this->assertSame('Invoice', $document->domDocument()->documentElement->localName);
    }

    public function testLoaderUsesTheSingleParseFactoryPath(): void
    {
        $factorySource = $this->sourceForMethod(UblXmlDocument::class, 'fromXml');
        $loaderSource  = $this->sourceForMethod(UblXmlDocumentLoader::class, 'parse');
        $parserSource  = $this->sourceForMethod(
            SecureXmlDocumentParser::class,
            'parse'
        );

        // The parser is deliberately static and non-injectable so callers cannot replace the DTD guard.
        $this->assertSame(1, preg_match_all('/SecureXmlDocumentParser\s*::\s*parse\s*\(/', $factorySource));
        $this->assertStringContainsString('UblXmlDocument::fromXml($xml)', $loaderSource);
        $this->assertDoesNotMatchRegularExpression('/fromSecurelyParsedXml|SecureXmlDocumentParser/', $loaderSource);
        $this->assertSame(1, preg_match_all('/->loadXML\s*\(/', $parserSource));
        $this->assertTrue((new ReflectionMethod(UblXmlDocument::class, '__construct'))->isPrivate());
    }

    public function testUblDocumentKeepsItsDomSnapshotPrivate(): void
    {
        $xml      = $this->documentXml('Invoice');
        $document = UblXmlDocument::fromXml($xml);
        $returned = $document->domDocument();
        $returned->documentElement->setAttribute('returnedMutation', 'visible');

        $validationDocument = $document->domDocument();
        $this->assertFalse($validationDocument->documentElement->hasAttribute('returnedMutation'));
        $this->assertSame($xml, $document->xml());
        $this->assertSame('Invoice', $document->kind());
    }

    public function testRejectsUnsupportedDocumentTypesAndNamespaces(): void
    {
        $loader = new UblXmlDocumentLoader();

        $this->expectException(UnsupportedDocumentTypeException::class);
        $loader->parse($this->documentXml('Order'));
    }

    public function testRejectsUnsupportedNamespaces(): void
    {
        $this->expectException(UnsupportedDocumentTypeException::class);

        (new UblXmlDocumentLoader())->parse('<Invoice xmlns="urn:example:invalid"/>');
    }

    public function testRejectsMalformedXml(): void
    {
        $this->expectException(InvalidXmlException::class);
        $this->expectExceptionMessage('not well-formed');

        (new UblXmlDocumentLoader())->parse('<Invoice>');
    }

    public function testRejectsXmlAboveTheConfiguredSizeLimitBeforeParsing(): void
    {
        $this->expectException(InvalidXmlException::class);

        (new UblXmlDocumentLoader())->parse(str_repeat(' ', EFakturaDocumentLimits::MAX_XML_SIZE_BYTES + 1));
    }

    public function testAcceptsAValidDocumentExactlyAtTheConfiguredSizeLimit(): void
    {
        $opening   = '<Invoice xmlns="urn:oasis:names:specification:ubl:schema:xsd:Invoice-2">';
        $closing   = '</Invoice>';
        $remaining = EFakturaDocumentLimits::MAX_XML_SIZE_BYTES - strlen($opening) - strlen($closing);
        $body      = '';
        $hasChunk  = false;
        while ($remaining > 0) {
            $separator     = $hasChunk ? '<!--padding-->' : '';
            $contentLength = min(1_000_000, $remaining - strlen($separator) - 12);
            $chunk         = $separator . '<![CDATA[' . str_repeat('x', $contentLength) . ']]>';
            $body .= $chunk;
            $remaining -= strlen($chunk);
            $hasChunk = true;
        }

        $xml = $opening . $body . $closing;

        $this->assertSame(EFakturaDocumentLimits::MAX_XML_SIZE_BYTES, strlen($xml));
        $this->assertSame('Invoice', (new UblXmlDocumentLoader())->parse($xml)->kind());
    }

    public function testRejectsDtdAndEntityDeclarations(): void
    {
        $this->expectException(InvalidXmlException::class);
        $this->expectExceptionMessage('prohibited DTD');

        (new UblXmlDocumentLoader())->parse(
            '<!DOCTYPE Invoice [<!ENTITY synthetic SYSTEM "file:///synthetic">]>'
                . $this->documentXml('Invoice')
        );
    }

    public function testUnsafeXmlExceptionDescribesTheActualDtdRejection(): void
    {
        try {
            SecureXmlDocumentParser::parse('<!DOCTYPE Invoice><Invoice/>');
        } catch (XmlParseException $exception) {
            $this->assertSame('XML contains a prohibited DTD declaration.', $exception->getMessage());
            $this->assertFalse(method_exists(XmlParseException::class, 'isUnsafe'));

            return;
        }

        $this->fail('A document type declaration must be rejected.');
    }

    public function testRejectsDtdDeclarationsInUtf16AndUtf32Inputs(): void
    {
        $xml = '<!DOCTYPE Invoice [<!ENTITY synthetic "value">]>'
            . '<Invoice xmlns="urn:oasis:names:specification:ubl:schema:xsd:Invoice-2">&synthetic;</Invoice>';

        $encodedInputs = [
            'UTF-16LE with BOM'    => "\xFF\xFE" . mb_convert_encoding($xml, 'UTF-16LE', 'UTF-8'),
            'UTF-16BE with BOM'    => "\xFE\xFF" . mb_convert_encoding($xml, 'UTF-16BE', 'UTF-8'),
            'UTF-16LE without BOM' => mb_convert_encoding($xml, 'UTF-16LE', 'UTF-8'),
            'UTF-16BE without BOM' => mb_convert_encoding($xml, 'UTF-16BE', 'UTF-8'),
            'UTF-32LE with BOM'    => "\xFF\xFE\x00\x00" . mb_convert_encoding($xml, 'UTF-32LE', 'UTF-8'),
            'UTF-32BE with BOM'    => "\x00\x00\xFE\xFF" . mb_convert_encoding($xml, 'UTF-32BE', 'UTF-8'),
            'UTF-32LE without BOM' => mb_convert_encoding($xml, 'UTF-32LE', 'UTF-8'),
            'UTF-32BE without BOM' => mb_convert_encoding($xml, 'UTF-32BE', 'UTF-8'),
        ];

        foreach ($encodedInputs as $encoding => $encodedXml) {
            try {
                (new UblXmlDocumentLoader())->parse($encodedXml);
            } catch (InvalidXmlException $exception) {
                $this->assertTrue($exception->isUnsafe(), $encoding . ' input must be classified as unsafe.');

                continue;
            }

            $this->fail('A DTD in ' . $encoding . ' input was accepted.');
        }
    }

    public function testAllowsDtdLikeTextInsideCdata(): void
    {
        $xml = '<Invoice xmlns="urn:oasis:names:specification:ubl:schema:xsd:Invoice-2">'
            . '<![CDATA[<!DOCTYPE is ordinary text here]]></Invoice>';

        $document = (new UblXmlDocumentLoader())->parse($xml);

        $this->assertSame('Invoice', $document->domDocument()->documentElement->localName);
    }

    private function documentXml(string $kind): string
    {
        return '<' . $kind . ' xmlns="urn:oasis:names:specification:ubl:schema:xsd:' . $kind . '-2"/>';
    }

    private function sourceForMethod(string $className, string $methodName): string
    {
        $method = new ReflectionMethod($className, $methodName);
        $lines  = file($method->getFileName());

        if ($lines === false) {
            $this->fail('Could not read the source for ' . $className . '::' . $methodName . '().');
        }

        return implode('', array_slice(
            $lines,
            $method->getStartLine() - 1,
            $method->getEndLine() - $method->getStartLine() + 1
        ));
    }
}
