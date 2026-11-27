<?php

namespace DreaM1\EfakturaConnector\Tests\Unit\Support\Xml;

use DreaM1\EfakturaConnector\Support\Exceptions\InvalidInputException;
use DreaM1\EfakturaConnector\Support\Xml\XmlCharacters;
use PHPUnit\Framework\TestCase;

/** Checks the XML 1.0 character rules applied before serialization. */
class XmlCharactersTest extends TestCase
{
    /**
     * @dataProvider validValues
     */
    public function testAcceptsCharactersAllowedByXml(string $value): void
    {
        $this->assertTrue(XmlCharacters::isValid($value));
        XmlCharacters::assertValid($value, 'Value');
    }

    /** @return array<string, array{string}> */
    public static function validValues(): array
    {
        return [
            'empty'            => [''],
            'ascii and markup' => ['a & b < c > "d" \'e\''],
            'tab, LF and CR'   => ["a\tb\nc\rd"],
            'diacritics'       => ['Žilina, Košice, Ľubovňa'],
            'astral character' => ["emoji \u{1F600}"],
            'range boundaries' => ["\u{D7FF}\u{E000}\u{FFFD}\u{10FFFF}"],
        ];
    }

    /**
     * @dataProvider invalidValues
     */
    public function testRejectsCharactersXmlCannotRepresent(string $value): void
    {
        $this->assertFalse(XmlCharacters::isValid($value));

        $this->expectException(InvalidInputException::class);
        $this->expectExceptionMessage('Value must be valid UTF-8');

        XmlCharacters::assertValid($value, 'Value');
    }

    /** @return array<string, array{string}> */
    public static function invalidValues(): array
    {
        return [
            'NUL'               => ["a\x00b"],
            'vertical tab'      => ["a\x0Bb"],
            'form feed'         => ["a\x0Cb"],
            'escape'            => ["a\x1Bb"],
            'noncharacter FFFE' => ["a\u{FFFE}b"],
            'noncharacter FFFF' => ["a\u{FFFF}b"],
            'malformed UTF-8'   => ["bad\xC3\x28utf8"],
            'lone continuation' => ["\x80"],
        ];
    }
}
