<?php

namespace DreaM1\EfakturaConnector\Tests\Unit\Core\Configuration;

use DreaM1\EfakturaConnector\Core\Configuration\HttpResponseBodyLimit;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/** Tests the configured HTTP response body size value. */
class HttpResponseBodyLimitTest extends TestCase
{
    public function testCreatesLimitsAtSupportedBoundaries(): void
    {
        $minimum = HttpResponseBodyLimit::fromBytes(1);
        $maximum = HttpResponseBodyLimit::fromBytes(HttpResponseBodyLimit::MAX_BYTES);

        $this->assertSame(1, $minimum->bytes());
        $this->assertSame(HttpResponseBodyLimit::MAX_BYTES, $maximum->bytes());
        $this->assertSame(
            HttpResponseBodyLimit::DEFAULT_BYTES,
            HttpResponseBodyLimit::default()->bytes()
        );
        $this->assertTrue($minimum->equals(HttpResponseBodyLimit::fromBytes(1)));
        $this->assertFalse($minimum->equals($maximum));
    }

    /**
     * Rejects values outside the supported response size range.
     *
     * @param int $bytes Invalid size in bytes.
     * @dataProvider invalidLimits
     */
    public function testRejectsLimitsOutsideSupportedRange(int $bytes): void
    {
        $this->expectException(InvalidArgumentException::class);

        HttpResponseBodyLimit::fromBytes($bytes);
    }

    /** @return array<array-key, array<array-key, int>> Invalid limits. */
    public function invalidLimits(): array
    {
        return [
            'zero bytes'     => [0],
            'negative bytes' => [-1],
            'over maximum'   => [HttpResponseBodyLimit::MAX_BYTES + 1],
        ];
    }

    public function testDetectsChunkOverflowWithoutAddingByteCounts(): void
    {
        $limit = HttpResponseBodyLimit::fromBytes(5);

        $this->assertFalse($limit->wouldExceed(0, 5));
        $this->assertTrue($limit->wouldExceed(5, 1));
        $this->assertTrue($limit->wouldExceed(4, 2));
    }

    /**
     * Rejects negative transport byte counts.
     *
     * @param int $receivedBytes  Number of bytes already received.
     * @param int $nextChunkBytes Number of bytes in the next chunk.
     * @dataProvider invalidByteCounts
     */
    public function testRejectsNegativeByteCounts(int $receivedBytes, int $nextChunkBytes): void
    {
        $this->expectException(InvalidArgumentException::class);

        HttpResponseBodyLimit::fromBytes(5)->wouldExceed($receivedBytes, $nextChunkBytes);
    }

    /** @return array<array-key, array<array-key, int>> Invalid byte count pairs. */
    public function invalidByteCounts(): array
    {
        return [
            'negative received bytes' => [-1, 0],
            'negative chunk bytes'    => [0, -1],
        ];
    }
}
