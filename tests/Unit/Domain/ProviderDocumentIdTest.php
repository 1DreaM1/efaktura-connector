<?php

namespace DreaM1\EfakturaConnector\Tests\Unit\Domain;

use DreaM1\EfakturaConnector\Domain\EFaktura\ProviderDocumentId;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/** Checks validation, preservation and equality of provider document identifiers. */
class ProviderDocumentIdTest extends TestCase
{
    public function testPreservesOpaqueProviderValueAndComparesByValue(): void
    {
        $value = 'provider/document-42';
        $id    = ProviderDocumentId::fromString($value);

        $this->assertSame($value, $id->value());
        $this->assertTrue($id->equals(ProviderDocumentId::fromString($value)));
        $this->assertFalse($id->equals(ProviderDocumentId::fromString('provider/document-43')));
    }

    /**
     * Rejects blank provider document identifier values.
     *
     * @param string $value Candidate identifier value.
     * @dataProvider blankValues
     */
    public function testRejectsBlankIdentifiers(string $value): void
    {
        $this->expectException(InvalidArgumentException::class);
        ProviderDocumentId::fromString($value);
    }

    /**
     * Provides blank provider document identifiers that must be rejected.
     *
     * @return array<array-key, array<array-key, mixed>> Blank identifier cases.
     */
    public function blankValues(): array
    {
        return [
            'empty'  => [''],
            'spaces' => [" \t\n"],
        ];
    }
}
