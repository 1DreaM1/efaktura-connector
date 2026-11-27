<?php

namespace DreaM1\EfakturaConnector\Core\Pagination;

use DreaM1\EfakturaConnector\Support\StringValue;

/** Opaque provider continuation token passed unchanged between pages. */
final class PageToken
{
    use StringValue;

    protected static function normalize(string $value): string
    {
        return $value;
    }
}
