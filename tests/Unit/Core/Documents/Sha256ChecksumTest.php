<?php

namespace DreaM1\EfakturaConnector\Tests\Unit\Core\Documents;

use DreaM1\EfakturaConnector\Core\Documents\Sha256Checksum;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/** Tests SHA-256 checksum value behavior. */
class Sha256ChecksumTest extends TestCase
{
    public function testCreatesChecksumFromContentAndString(): void
    {
        $content  = '<Invoice>synthetic</Invoice>';
        $checksum = Sha256Checksum::fromContent($content);

        $this->assertSame(hash('sha256', $content), $checksum->value());
        $this->assertSame($checksum->value(), Sha256Checksum::fromString($checksum->value())->value());
        $this->assertTrue($checksum->matchesContent($content));
        $this->assertFalse($checksum->matchesContent('<Invoice/>'));
    }

    public function testRejectsMalformedChecksumString(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('lowercase SHA-256');

        Sha256Checksum::fromString(str_repeat('A', 64));
    }
}
