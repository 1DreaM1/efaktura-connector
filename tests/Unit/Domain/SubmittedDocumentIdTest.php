<?php

namespace DreaM1\EfakturaConnector\Tests\Unit\Domain;

use DreaM1\EfakturaConnector\Domain\EFaktura\SubmittedDocumentId;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/** Tests the SubmittedDocumentId value object. */
class SubmittedDocumentIdTest extends TestCase
{
    public function testPreservesTheSenderAssignedIdentifier(): void
    {
        $id = SubmittedDocumentId::fromString(' invoice-42 ');

        $this->assertSame(' invoice-42 ', $id->value());
    }

    public function testComparesIdentifiersByValue(): void
    {
        $id = SubmittedDocumentId::fromString('invoice-42');

        $this->assertTrue($id->equals(SubmittedDocumentId::fromString('invoice-42')));
        $this->assertFalse($id->equals(SubmittedDocumentId::fromString('invoice-43')));
    }

    /**
     * @dataProvider blankValues
     * @param string $value Blank identifier candidate.
     */
    public function testRejectsBlankIdentifiers(string $value): void
    {
        $this->expectException(InvalidArgumentException::class);

        SubmittedDocumentId::fromString($value);
    }

    /** @return array<string, array{string}> */
    public static function blankValues(): array
    {
        return [
            'empty'                => [''],
            'spaces'               => ['  '],
            'tabs and line breaks' => ["\t\r\n"],
        ];
    }
}
