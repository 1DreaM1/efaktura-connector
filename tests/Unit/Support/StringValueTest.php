<?php

namespace DreaM1\EfakturaConnector\Tests\Unit\Support;

use DreaM1\EfakturaConnector\Core\Documents\IdempotencyKey;
use DreaM1\EfakturaConnector\Core\IntegratorApi\IntegratorId;
use DreaM1\EfakturaConnector\Core\Pagination\PageToken;
use DreaM1\EfakturaConnector\Domain\EFaktura\ProviderDocumentId;
use DreaM1\EfakturaConnector\Support\Exceptions\InvalidInputException;
use PHPUnit\Framework\TestCase;

/** The shared string value behavior used by the identifier value objects. */
final class StringValueTest extends TestCase
{
    public function testStoresAndComparesTheNormalizedValue(): void
    {
        $this->assertSame('cursor', PageToken::fromString('cursor')->value());
        $this->assertTrue(PageToken::fromString('a')->equals(PageToken::fromString('a')));
        $this->assertFalse(PageToken::fromString('a')->equals(PageToken::fromString('b')));
    }

    public function testNormalizationRunsBeforeTheValueIsStored(): void
    {
        $this->assertSame('integrator-1', IntegratorId::fromString('  integrator-1 ')->value());
    }

    public function testValidationRejectsUnacceptableStrings(): void
    {
        $this->expectException(InvalidInputException::class);

        ProviderDocumentId::fromString('   ');
    }

    public function testValidationIsPerType(): void
    {
        $this->expectException(InvalidInputException::class);

        IdempotencyKey::fromString('not-a-uuid');
    }
}
