<?php

namespace DreaM1\EfakturaConnector\Core\Webhooks;

use DreaM1\EfakturaConnector\Domain\EFaktura\Webhooks\WebhookIdentifier;

/** Input data for deleting a webhook registration. */
final class DeleteWebhookRequest
{
    private WebhookIdentifier $webhookId;

    /**
     * Creates a request to delete the identified webhook registration.
     *
     * @param WebhookIdentifier $webhookId Identifier of the webhook subscription.
     */
    public function __construct(WebhookIdentifier $webhookId)
    {
        $this->webhookId = $webhookId;
    }

    /**
     * Returns the ID of the webhook registration to delete.
     *
     * @return WebhookIdentifier
     */
    public function webhookId(): WebhookIdentifier
    {
        return $this->webhookId;
    }
}
