<?php

namespace DreaM1\EfakturaConnector\Core\Webhooks;

use DreaM1\EfakturaConnector\Core\Pagination\PageSize;
use DreaM1\EfakturaConnector\Core\Pagination\PageToken;

/** Pagination input for listing a participant's webhook registrations. */
final class ListWebhooksRequest
{
    private ?PageToken $pageToken;
    private ?PageSize $limit;

    /**
     * Creates a paginated webhook-list request.
     *
     * @param PageToken|null $pageToken Continuation token from the preceding page, or null for the first page.
     * @param PageSize|null  $limit     Maximum number of results or findings to return.
     */
    public function __construct(?PageToken $pageToken = null, ?PageSize $limit = null)
    {
        $this->pageToken = $pageToken;
        $this->limit     = $limit;
    }

    /**
     * Returns the continuation token for this page, or null for the first page.
     *
     * @return PageToken|null
     */
    public function pageToken(): ?PageToken
    {
        return $this->pageToken;
    }

    /**
     * Returns the requested page size, or null to use the provider default.
     *
     * @return PageSize|null
     */
    public function limit(): ?PageSize
    {
        return $this->limit;
    }
}
