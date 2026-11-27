<?php

namespace DreaM1\EfakturaConnector\Tests\Unit\Core\Documents;

use DreaM1\EfakturaConnector\Core\Documents\IdempotencyKey;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/** Checks UUID validation, value preservation and equality for idempotency keys. */
class IdempotencyKeyTest extends TestCase
{
    public function testPreservesItsUuidAndComparesByValue(): void
    {
        $value = '00000000-0000-4000-8000-000000000001';
        $key   = IdempotencyKey::fromString($value);

        $this->assertSame($value, $key->value());
        $this->assertTrue($key->equals(IdempotencyKey::fromString($value)));
        $this->assertFalse($key->equals(IdempotencyKey::fromString('00000000-0000-4000-8000-000000000002')));
    }

    /**
     * Rejects idempotency keys that are not valid UUID strings.
     *
     * @param string $value Candidate key value.
     * @dataProvider invalidKeys
     */
    public function testRejectsValuesOutsideTheUuidFormat(string $value): void
    {
        $this->expectException(InvalidArgumentException::class);
        IdempotencyKey::fromString($value);
    }

    /**
     * Provides idempotency keys that do not match the required UUID format.
     *
     * @return array<array-key, array<array-key, mixed>> Invalid key cases.
     */
    public function invalidKeys(): array
    {
        return [
            'arbitrary text'           => ['request-key-1'],
            'empty value'              => [''],
            'invalid UUID version'     => ['00000000-0000-0000-8000-000000000001'],
            'invalid UUID variant'     => ['00000000-0000-4000-7000-000000000001'],
            'suffix'                   => ['00000000-0000-4000-8000-000000000001-extra'],
            'unsafe header characters' => ["00000000-0000-4000-8000-000000000001\r\nX-Test: bad"],
        ];
    }

    public function testUpperCaseSpellingIsNormalizedToTheSameKey(): void
    {
        $lower = IdempotencyKey::fromString('0a1b2c3d-4e5f-4a6b-8c7d-9e0f1a2b3c4d');
        $upper = IdempotencyKey::fromString('0A1B2C3D-4E5F-4A6B-8C7D-9E0F1A2B3C4D');

        $this->assertSame('0a1b2c3d-4e5f-4a6b-8c7d-9e0f1a2b3c4d', $upper->value());
        $this->assertTrue($lower->equals($upper));
    }
}
