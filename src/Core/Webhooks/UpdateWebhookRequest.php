<?php

namespace DreaM1\EfakturaConnector\Core\Webhooks;

use DreaM1\EfakturaConnector\Domain\EFaktura\Webhooks\WebhookIdentifier;
use DreaM1\EfakturaConnector\Support\Exceptions\InvalidInputException;

/** Input data for changing a webhook registration. */
final class UpdateWebhookRequest
{
    private WebhookIdentifier $webhookId;
    private WebhookUpdate $update;

    /**
     * Creates a partial update for an existing webhook registration.
     *
     * Omitted fields remain unchanged; a present null value is retained for provider serialization.
     *
     * @param  WebhookIdentifier     $webhookId Identifier of the webhook subscription.
     * @param  WebhookUpdate         $update    Typed webhook fields to update.
     * @throws InvalidInputException when the update does not contain any fields
     */
    public function __construct(WebhookIdentifier $webhookId, WebhookUpdate $update)
    {
        if ($update->isEmpty()) {
            throw new InvalidInputException('Webhook update must contain at least one field.');
        }

        $this->webhookId = $webhookId;
        $this->update    = $update;
    }

    /** Returns the ID of the webhook registration to update. */
    public function webhookId(): WebhookIdentifier
    {
        return $this->webhookId;
    }

    /** Returns the typed partial update. */
    public function update(): WebhookUpdate
    {
        return $this->update;
    }
}
