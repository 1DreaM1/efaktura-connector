<?php

namespace DreaM1\EfakturaConnector\Tests\Unit\Domain\Ubl\Draft;

use DreaM1\EfakturaConnector\Domain\Ubl\Draft\MimeType;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/** Tests MIME type value validation. */
class MimeTypeTest extends TestCase
{
    public function testPreservesValidMimeTypeRepresentation(): void
    {
        $mimeType = MimeType::fromString('Application/PDF');

        $this->assertSame('Application/PDF', $mimeType->value());
    }

    /**
     * @dataProvider invalidMimeTypeProvider
     */
    public function testRejectsInvalidMimeTypeRepresentations(string $mimeType): void
    {
        $this->expectException(InvalidArgumentException::class);

        MimeType::fromString($mimeType);
    }

    /** @return array<string, array{string}> Invalid MIME type values and their case names. */
    public function invalidMimeTypeProvider(): array
    {
        return [
            'missing subtype'              => ['application/'],
            'missing slash'                => ['application-pdf'],
            'parameter is not a MIME code' => ['application/pdf; charset=utf-8'],
            'whitespace'                   => [' application/pdf'],
        ];
    }
}
