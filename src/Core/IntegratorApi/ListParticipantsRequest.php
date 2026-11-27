<?php

namespace DreaM1\EfakturaConnector\Core\IntegratorApi;

use DreaM1\EfakturaConnector\Core\Pagination\PageSize;
use DreaM1\EfakturaConnector\Core\Pagination\PageToken;
use DreaM1\EfakturaConnector\Support\Exceptions\InvalidInputException;

/** Cursor filters for one page of integrator participants. */
final class ListParticipantsRequest
{
    private ?PageToken $pageToken;
    private ?PageSize $limit;

    /**
     * Creates cursor and page-size filters for the participant list.
     *
     * @param PageToken|null $pageToken Continuation token from a previous page.
     * @param PageSize|null  $limit     Maximum number of participants requested.
     */
    public function __construct(?PageToken $pageToken = null, ?PageSize $limit = null)
    {
        $this->pageToken = $pageToken;
        $this->limit     = $limit;
    }

    /** Returns the continuation token, or null for the first page. */
    public function pageToken(): ?PageToken
    {
        return $this->pageToken;
    }

    /** Returns the requested page size, or null to use the provider default. */
    public function limit(): ?PageSize
    {
        return $this->limit;
    }
}
