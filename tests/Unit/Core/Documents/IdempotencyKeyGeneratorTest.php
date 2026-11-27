<?php

namespace DreaM1\EfakturaConnector\Tests\Unit\Core\Documents;

use DreaM1\EfakturaConnector\Core\Documents\IdempotencyKey;
use DreaM1\EfakturaConnector\Core\Documents\IdempotencyKeyGenerator;
use PHPUnit\Framework\TestCase;

/** Checks that generated document-submission idempotency keys use valid UUID version 4 values. */
class IdempotencyKeyGeneratorTest extends TestCase
{
    public function testGeneratesAValidUuidVersion4(): void
    {
        $key = IdempotencyKeyGenerator::generate();

        $this->assertInstanceOf(IdempotencyKey::class, $key);
        $this->assertMatchesRegularExpression(
            '/\A[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}\z/i',
            $key->value()
        );
    }
}
