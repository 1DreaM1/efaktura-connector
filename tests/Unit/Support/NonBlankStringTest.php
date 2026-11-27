<?php

namespace DreaM1\EfakturaConnector\Tests\Unit\Support;

use DreaM1\EfakturaConnector\Support\NonBlankString;
use PHPUnit\Framework\TestCase;

/** Tests validation of non-blank string values. */
class NonBlankStringTest extends TestCase
{
    public function testRejectsEmptyAndWhitespaceOnlyStrings(): void
    {
        $this->assertFalse(NonBlankString::isNotBlank(''));
        $this->assertFalse(NonBlankString::isNotBlank(" \t\n"));
        $this->assertFalse(NonBlankString::isNotBlank("\u{00A0}\u{2003}"));
    }

    public function testAcceptsStringsWithWhitespaceAroundTheirContent(): void
    {
        $this->assertTrue(NonBlankString::isNotBlank(' identifier '));
    }
}
