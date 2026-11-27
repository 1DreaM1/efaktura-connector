<?php

namespace DreaM1\EfakturaConnector\Tests\Unit\Domain;

use DreaM1\EfakturaConnector\Domain\EFaktura\ProviderMessageId;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/** Checks validation and value semantics for provider message identifiers. */
class ProviderMessageIdTest extends TestCase
{
    public function testPreservesOpaqueProviderValueAndComparesIds(): void
    {
        $messageId = ProviderMessageId::fromString(' ap-message/42 ');

        $this->assertSame(' ap-message/42 ', $messageId->value());
        $this->assertTrue($messageId->equals(ProviderMessageId::fromString(' ap-message/42 ')));
        $this->assertFalse($messageId->equals(ProviderMessageId::fromString('ap-message/42')));
    }

    public function testRejectsBlankValue(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Provider message ID must not be empty.');

        ProviderMessageId::fromString(" \t\n");
    }
}
