<?php

namespace DreaM1\EfakturaConnector\Tests\Unit\Domain\Ubl;

use DreaM1\EfakturaConnector\Domain\Ubl\Draft\Base64DocumentContent;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/** Tests the Base64DocumentContent value object. */
class Base64DocumentContentTest extends TestCase
{
    public function testPreservesValidBase64Content(): void
    {
        $content = Base64DocumentContent::fromString('c3ludGhldGlj');

        $this->assertSame('c3ludGhldGlj', $content->value());
    }

    public function testPreservesXmlWhitespaceInValidBase64Content(): void
    {
        $value = "YQ==\r\n";

        $this->assertSame($value, Base64DocumentContent::fromString($value)->value());
    }

    /**
     * @dataProvider invalidBase64Values
     * @param string $value Invalid Base64 text.
     */
    public function testRejectsInvalidBase64Content(string $value): void
    {
        $this->expectException(InvalidArgumentException::class);

        Base64DocumentContent::fromString($value);
    }

    /** @return array<string, array{string}> */
    public static function invalidBase64Values(): array
    {
        return [
            'invalid alphabet character' => ['YQ$='],
            'incomplete padding'         => ['YQ='],
            'missing padding'            => ['YQ'],
            'extra padding'              => ['YQ==='],
            'non XML whitespace'         => ["YQ==\v"],
            'empty content'              => [''],
            'whitespace only'            => [" \t\r\n"],
        ];
    }
}
