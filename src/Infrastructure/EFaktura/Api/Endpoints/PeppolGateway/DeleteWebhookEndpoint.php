<?php

namespace DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Endpoints\PeppolGateway;

use DreaM1\EfakturaConnector\Domain\EFaktura\Participant;
use DreaM1\EfakturaConnector\Domain\EFaktura\Webhooks\WebhookIdentifier;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\ApiRequest;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Endpoints\AbstractEndpoint;

/**
 * DELETE /v1/webhooks/{webhookId} → 204 No Content
 */
class DeleteWebhookEndpoint extends AbstractEndpoint
{
    /**
     * Deletes the webhook for the participant; a successful provider response has no body.
     *
     * @param  Participant       $participant Participant that owns the webhook.
     * @param  WebhookIdentifier $webhookId   Identifier of the registration to delete.
     * @return void
     */
    public function send(Participant $participant, WebhookIdentifier $webhookId): void
    {
        $this->api->send(
            ApiRequest::delete(PeppolGatewayApiPaths::WEBHOOK, ['webhookId' => $webhookId->value()])
                ->forParticipant($participant)
        );
    }
}
