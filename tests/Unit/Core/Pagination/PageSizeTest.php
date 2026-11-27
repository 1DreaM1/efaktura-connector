<?php

namespace DreaM1\EfakturaConnector\Tests\Unit\Core\Pagination;

use DreaM1\EfakturaConnector\Core\Pagination\PageSize;
use DreaM1\EfakturaConnector\Support\Exceptions\InvalidInputException;
use PHPUnit\Framework\TestCase;

/** Tests the shared OpenAPI page-size boundary. */
final class PageSizeTest extends TestCase
{
    public function testExposesTheOpenApiInt32Maximum(): void
    {
        $this->assertSame(2147483647, PageSize::MAX_OPENAPI_INT32_VALUE);
    }

    public function testRejectsPageSizesAboveTheOpenApiInt32Maximum(): void
    {
        $this->assertInt32OverflowIsAvailable();

        $this->expectException(InvalidInputException::class);
        $this->expectExceptionMessage('Page size must fit the OpenAPI int32 range.');

        PageSize::fromInt(PageSize::MAX_OPENAPI_INT32_VALUE + 1);
    }

    public function testAcceptsThePageSizeAtTheOpenApiInt32Maximum(): void
    {
        $this->assertSame(
            PageSize::MAX_OPENAPI_INT32_VALUE,
            PageSize::fromInt(PageSize::MAX_OPENAPI_INT32_VALUE)->value()
        );
    }

    private function assertInt32OverflowIsAvailable(): void
    {
        if (PHP_INT_SIZE < 8) {
            $this->markTestSkipped('The PHP runtime cannot represent a value above the signed int32 maximum.');
        }
    }
}
