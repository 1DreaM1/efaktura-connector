<?php

namespace DreaM1\EfakturaConnector\Tests\Unit\Domain;

use DreaM1\EfakturaConnector\Domain\EFaktura\EFakturaDocumentLimits;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/** Checks the constants-only document limits type cannot be instantiated or extended. */
class EFakturaDocumentLimitsTest extends TestCase
{
    public function testLimitsTypeIsFinalAndCannotBeInstantiated(): void
    {
        $reflection = new ReflectionClass(EFakturaDocumentLimits::class);

        $this->assertTrue($reflection->isFinal());
        $this->assertFalse($reflection->isInstantiable());
    }
}
