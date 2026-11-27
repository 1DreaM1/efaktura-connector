<?php

namespace DreaM1\EfakturaConnector\Tests\Unit\Domain;

use DreaM1\EfakturaConnector\Domain\EFaktura\MessageLevelStatusIdentifier;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/** Checks validation and preservation of opaque message-level status identifiers. */
class MessageLevelStatusIdentifierTest extends TestCase
{
    public function testPreservesTheProviderValueAndComparesIdentifiers(): void
    {
        $identifier = MessageLevelStatusIdentifier::fromString(' status/42 ');

        $this->assertSame(' status/42 ', $identifier->value());
        $this->assertTrue($identifier->equals(MessageLevelStatusIdentifier::fromString(' status/42 ')));
        $this->assertFalse($identifier->equals(MessageLevelStatusIdentifier::fromString('status/42')));
    }

    public function testRejectsBlankValue(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Message-level status identifier must not be empty.');

        MessageLevelStatusIdentifier::fromString(" \t\n");
    }
}
