<?php

namespace DreaM1\EfakturaConnector\Core\Pagination;

/** Opaque continuation token shared by typed paginated page values. */
final class PageCursor
{
    private ?PageToken $nextPageToken;

    /**
     * Stores the provider's continuation token without parsing or normalizing it.
     *
     * @param PageToken|null $nextPageToken Opaque token for the next page, or null when pagination is complete.
     */
    public function __construct(?PageToken $nextPageToken)
    {
        $this->nextPageToken = $nextPageToken;
    }

    /** Returns the token for requesting the next page, or null when there is no next page. */
    public function nextPageToken(): ?PageToken
    {
        return $this->nextPageToken;
    }
}
