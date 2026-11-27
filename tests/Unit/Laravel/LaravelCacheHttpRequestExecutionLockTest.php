<?php

namespace DreaM1\EfakturaConnector\Tests\Unit\Laravel;

use DreaM1\EfakturaConnector\Laravel\Transport\LaravelCacheHttpRequestExecutionLock;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/** Tests constructor invariants for the Laravel cache execution-lock adapter. */
class LaravelCacheHttpRequestExecutionLockTest extends TestCase
{
    public function testRejectsNonPositiveLease(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('lease must be positive');

        new LaravelCacheHttpRequestExecutionLock(null, 0, 0);
    }

    public function testRejectsNegativeWaitDuration(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('wait time must not be negative');

        new LaravelCacheHttpRequestExecutionLock(null, 1, -1);
    }

    public function testAcceptsMinimumLeaseAndZeroWait(): void
    {
        $lock = new LaravelCacheHttpRequestExecutionLock(null, 1, 0);

        $this->assertInstanceOf(LaravelCacheHttpRequestExecutionLock::class, $lock);
    }
}
