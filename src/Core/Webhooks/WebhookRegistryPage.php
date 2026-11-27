<?php

namespace DreaM1\EfakturaConnector\Core\Webhooks;

use DreaM1\EfakturaConnector\Core\Pagination\PageCursor;
use DreaM1\EfakturaConnector\Core\Pagination\PageToken;

/** One page of webhook registrations and its optional continuation token. */
final class WebhookRegistryPage
{
    private WebhookList $webhooks;
    private PageCursor $cursor;

    /**
     * @param WebhookList    $webhooks      Webhook subscriptions to include in the result.
     * @param PageToken|null $nextPageToken Continuation token for the next page, when available.
     */
    public function __construct(WebhookList $webhooks, ?PageToken $nextPageToken)
    {
        $this->webhooks = $webhooks;
        $this->cursor   = new PageCursor($nextPageToken);
    }

    /**
     * Returns the webhooks.
     *
     * @return WebhookList
     */
    public function webhooks(): WebhookList
    {
        return $this->webhooks;
    }

    /**
     * Returns the next page token.
     *
     * @return PageToken|null
     */
    public function nextPageToken(): ?PageToken
    {
        return $this->cursor->nextPageToken();
    }
}
