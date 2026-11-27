<?php

namespace DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Endpoints;

use DreaM1\EfakturaConnector\Core\Pagination\PageSize;
use DreaM1\EfakturaConnector\Core\Pagination\PageToken;

/** Maps typed pagination values to the `pageToken` and `limit` query parameters shared by every list operation. */
final class PageQuery
{
    private function __construct()
    {
    }

    /**
     * Builds the pagination part of a list query, omitting values that were not supplied.
     *
     * @param  PageToken|null            $pageToken Continuation token from a previous page.
     * @param  PageSize|null             $limit     Maximum number of items requested.
     * @return array<string, string|int>
     */
    public static function of(?PageToken $pageToken, ?PageSize $limit): array
    {
        $query = [];

        if ($pageToken !== null) {
            $query['pageToken'] = $pageToken->value();
        }

        if ($limit !== null) {
            $query['limit'] = $limit->value();
        }

        return $query;
    }
}
