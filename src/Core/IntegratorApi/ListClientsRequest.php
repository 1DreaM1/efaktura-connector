<?php

namespace DreaM1\EfakturaConnector\Core\IntegratorApi;

use DreaM1\EfakturaConnector\Core\Pagination\PageSize;
use DreaM1\EfakturaConnector\Core\Pagination\PageToken;
use DreaM1\EfakturaConnector\Support\Exceptions\InvalidInputException;

/** Query filters for one page of OAuth clients. */
final class ListClientsRequest
{
    private ?PageToken $pageToken;
    private ?PageSize $limit;
    private ?IntegratorClientStatus $status;

    /**
     * Creates cursor, page-size and lifecycle filters for the OAuth client list.
     *
     * @param PageToken|null              $pageToken Continuation token from a previous page.
     * @param PageSize|null               $limit     Maximum number of clients requested.
     * @param IntegratorClientStatus|null $status    Optional supported lifecycle status filter.
     */
    public function __construct(
        ?PageToken $pageToken = null,
        ?PageSize $limit = null,
        ?IntegratorClientStatus $status = null
    ) {
        $this->pageToken = $pageToken;
        $this->limit     = $limit;
        $this->status    = $status;
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

    /** Returns the optional client status filter. */
    public function status(): ?IntegratorClientStatus
    {
        return $this->status;
    }
}
