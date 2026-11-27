<?php

namespace DreaM1\EfakturaConnector\Tests\Unit\Core\Documents;

use DreaM1\EfakturaConnector\Core\Documents\SendDocumentsResult;
use DreaM1\EfakturaConnector\Domain\EFaktura\ProviderDocumentId;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/** Checks invariants of the Peppol Gateway batch result. */
final class SendDocumentsResultTest extends TestCase
{
    public function testAcceptedCountMustMatchGatewayIdentifiers(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new SendDocumentsResult(2, [ProviderDocumentId::fromString('gateway-1')]);
    }

    public function testAcceptsAConsistentCountAndIdentifierList(): void
    {
        $result = new SendDocumentsResult(2, [
            4 => ProviderDocumentId::fromString('gateway-1'),
            9 => ProviderDocumentId::fromString('gateway-2'),
        ]);

        $this->assertSame(2, $result->count());
        $this->assertCount(2, $result->identifiers());
        $this->assertSame([0, 1], array_keys($result->identifiers()));
    }

    public function testRejectsItemsThatAreNotProviderDocumentIds(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Gateway identifiers must be ProviderDocumentId instances.');

        new SendDocumentsResult(1, ['not an identifier']);
    }
}
