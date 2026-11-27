<?php

namespace DreaM1\EfakturaConnector\Tests\Unit\Core\Retry;

use DreaM1\EfakturaConnector\Core\Retry\RetryAfter;
use DreaM1\EfakturaConnector\Core\Retry\RetryDeadline;
use PHPUnit\Framework\TestCase;

/** Tests Retry-After header parsing. */
class RetryAfterTest extends TestCase
{
    public function testParsesDelayAsATypedDeadline(): void
    {
        $deadline = RetryAfter::deadline(['retry-after' => '30'], 1000);

        $this->assertInstanceOf(RetryDeadline::class, $deadline);
        $this->assertSame(1030, $deadline->unixTimestamp());
    }

    public function testParsesHttpDateAndClampsPastDates(): void
    {
        $future = RetryAfter::deadline(['Retry-After' => gmdate('D, d M Y H:i:s \G\M\T', 1100)], 1000);
        $past   = RetryAfter::deadline(['Retry-After' => gmdate('D, d M Y H:i:s \G\M\T', 900)], 1000);

        $this->assertSame(1100, $future->unixTimestamp());
        $this->assertSame(1000, $past->unixTimestamp());
    }

    public function testReturnsNullForMissingOrInvalidHeader(): void
    {
        $this->assertNull(RetryAfter::deadline([], 1000));
        $this->assertNull(RetryAfter::deadline(['Retry-After' => 'tomorrow'], 1000));
    }
}
