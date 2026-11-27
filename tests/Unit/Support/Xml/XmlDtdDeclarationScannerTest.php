<?php

namespace DreaM1\EfakturaConnector\Tests\Unit\Support\Xml;

use DreaM1\EfakturaConnector\Support\Xml\XmlDtdDeclarationScanner;
use PHPUnit\Framework\TestCase;

/** Tests DTD detection across XML prologs and byte encodings. */
class XmlDtdDeclarationScannerTest extends TestCase
{
    public function testDetectsDoctypeAfterCommentsAndProcessingInstructions(): void
    {
        $xml = '<!-- comment --><?stylesheet data?><!DOCTYPE Invoice><Invoice/>';

        $this->assertTrue(XmlDtdDeclarationScanner::containsDocumentTypeDeclaration($xml));
    }

    public function testIgnoresDoctypeLikeTextOutsideTheProlog(): void
    {
        $xml = '<!-- <!DOCTYPE comment> --><?probe <!DOCTYPE instruction?>'
            . '<Invoice><![CDATA[<!DOCTYPE data>]]></Invoice>';

        $this->assertFalse(XmlDtdDeclarationScanner::containsDocumentTypeDeclaration($xml));
    }

    /** @dataProvider dtdEncodings */
    public function testDetectsDoctypeInSupportedUnicodeEncodings(string $encoding, string $byteOrderMark): void
    {
        $xml        = '<?xml version="1.0"?><!DOCTYPE Invoice><Invoice/>';
        $encodedXml = $byteOrderMark . mb_convert_encoding($xml, $encoding, 'UTF-8');

        $this->assertTrue(XmlDtdDeclarationScanner::containsDocumentTypeDeclaration($encodedXml));
    }

    /** @return array<string, array{string, string}> XML encodings and byte-order marks for the data provider. */
    public function dtdEncodings(): array
    {
        return [
            'UTF-16BE with BOM'    => ['UTF-16BE', "\xFE\xFF"],
            'UTF-16LE with BOM'    => ['UTF-16LE', "\xFF\xFE"],
            'UTF-32BE with BOM'    => ['UTF-32BE', "\x00\x00\xFE\xFF"],
            'UTF-32LE with BOM'    => ['UTF-32LE', "\xFF\xFE\x00\x00"],
            'UTF-16BE without BOM' => ['UTF-16BE', ''],
            'UTF-16LE without BOM' => ['UTF-16LE', ''],
            'UTF-32BE without BOM' => ['UTF-32BE', ''],
            'UTF-32LE without BOM' => ['UTF-32LE', ''],
        ];
    }
}
