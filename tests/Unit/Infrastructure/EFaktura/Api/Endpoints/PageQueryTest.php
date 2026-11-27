<?php

namespace DreaM1\EfakturaConnector\Tests\Unit\Infrastructure\EFaktura\Api\Endpoints;

use DreaM1\EfakturaConnector\Core\Pagination\PageSize;
use DreaM1\EfakturaConnector\Core\Pagination\PageToken;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Endpoints\PageQuery;
use PHPUnit\Framework\TestCase;

/** Pagination values are mapped to the same query names for every list operation. */
final class PageQueryTest extends TestCase
{
    public function testOmitsValuesThatWereNotSupplied(): void
    {
        $this->assertSame([], PageQuery::of(null, null));
    }

    public function testMapsTokenAndLimit(): void
    {
        $this->assertSame(
            ['pageToken' => 'next page', 'limit' => 25],
            PageQuery::of(PageToken::fromString('next page'), PageSize::fromInt(25))
        );
    }

    public function testMapsEachValueIndependently(): void
    {
        $this->assertSame(['limit' => 5], PageQuery::of(null, PageSize::fromInt(5)));
        $this->assertSame(['pageToken' => 't'], PageQuery::of(PageToken::fromString('t'), null));
    }
}
