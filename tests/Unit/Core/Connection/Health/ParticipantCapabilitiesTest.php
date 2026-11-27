<?php

namespace DreaM1\EfakturaConnector\Tests\Unit\Core\Connection\Health;

use DreaM1\EfakturaConnector\Core\Connection\Health\ParticipantCapabilities;
use DreaM1\EfakturaConnector\Core\Connection\Health\ParticipantCapability;
use DreaM1\EfakturaConnector\Domain\EFaktura\PeppolDocumentTypeIdentifier;
use DreaM1\EfakturaConnector\Domain\EFaktura\PeppolProcessIdentifier;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/** Checks typed participant-capability collections and rejection of invalid items. */
class ParticipantCapabilitiesTest extends TestCase
{
    public function testCollectionPreservesTypedCapabilities(): void
    {
        $invoice      = new ParticipantCapability(PeppolDocumentTypeIdentifier::fromString('invoice'), PeppolProcessIdentifier::fromString('billing'), 'as4');
        $creditNote   = new ParticipantCapability(PeppolDocumentTypeIdentifier::fromString('credit-note'), PeppolProcessIdentifier::fromString('billing'), null);
        $capabilities = new ParticipantCapabilities([4 => $invoice, 9 => $creditNote]);

        $this->assertCount(2, $capabilities);
        $this->assertSame([$invoice, $creditNote], iterator_to_array($capabilities));
        $this->assertSame('invoice', $invoice->documentTypeId()->value());
        $this->assertSame('billing', $invoice->processId()->value());
        $this->assertSame('as4', $invoice->transportProfile());
    }

    public function testEmptyCollectionAndInvalidConstructorItems(): void
    {
        $this->assertCount(0, ParticipantCapabilities::empty());

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Participant capabilities must contain capability values.');
        new ParticipantCapabilities(['not a capability']);
    }
}
