<?php

namespace DreaM1\EfakturaConnector\Tests\Unit\Core\Pagination;

use DreaM1\EfakturaConnector\Core\Pagination\PageCursor;
use DreaM1\EfakturaConnector\Core\Pagination\PageToken;
use PHPUnit\Framework\TestCase;

/** Checks that provider continuation tokens remain opaque when stored in a page cursor. */
class PageCursorTest extends TestCase
{
    public function testPreservesOpaqueContinuationTokens(): void
    {
        $token = ' provider/cursor+opaque== ';

        $this->assertSame($token, (new PageCursor(PageToken::fromString($token)))->nextPageToken()->value());
        $this->assertSame('', (new PageCursor(PageToken::fromString('')))->nextPageToken()->value());
        $this->assertNull((new PageCursor(null))->nextPageToken());
    }
}
