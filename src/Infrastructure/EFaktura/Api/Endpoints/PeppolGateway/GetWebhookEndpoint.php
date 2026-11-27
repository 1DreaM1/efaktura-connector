<?php

namespace DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Endpoints\PeppolGateway;

use DreaM1\EfakturaConnector\Core\Webhooks\Webhook;
use DreaM1\EfakturaConnector\Domain\EFaktura\Participant;
use DreaM1\EfakturaConnector\Domain\EFaktura\Webhooks\WebhookIdentifier;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\ApiRequest;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Endpoints\AbstractEndpoint;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Response\PeppolGateway\WebhookFactory;

/**
 * Retrieves a registered Core webhook model from the Peppol Gateway.
 * GET /v1/webhooks/{webhookId} → 200 Webhook
 */
class GetWebhookEndpoint extends AbstractEndpoint
{
    /**
     * Sends the request to the provider endpoint and returns its mapped response.
     *
     * @param  Participant       $participant Participant that owns the webhook.
     * @param  WebhookIdentifier $webhookId   Identifier of the registration to retrieve.
     * @return Webhook
     */
    public function send(Participant $participant, WebhookIdentifier $webhookId): Webhook
    {
        return $this->api->json(
            ApiRequest::get(PeppolGatewayApiPaths::WEBHOOK, ['webhookId' => $webhookId->value()])
                ->forParticipant($participant),
            [WebhookFactory::class, 'fromArray']
        );
    }
}
