<?php

namespace DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Endpoints\PeppolGateway;

use DreaM1\EfakturaConnector\Core\Webhooks\UpdateWebhookRequest;
use DreaM1\EfakturaConnector\Core\Webhooks\Webhook;
use DreaM1\EfakturaConnector\Domain\EFaktura\Participant;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\ApiRequest;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Endpoints\AbstractEndpoint;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Response\PeppolGateway\WebhookFactory;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Serialization\WebhookRequestMapper;

/**
 * Updates a webhook and returns its Core model.
 * PATCH /v1/webhooks/{webhookId} → 200 Webhook
 */
class UpdateWebhookEndpoint extends AbstractEndpoint
{
    /**
     * Sends the request to the provider endpoint and returns its mapped response.
     *
     * @param  Participant          $participant Participant that owns the webhook.
     * @param  UpdateWebhookRequest $request     Webhook ID and partial update to apply.
     * @return Webhook
     */
    public function send(Participant $participant, UpdateWebhookRequest $request): Webhook
    {
        return $this->api->json(
            ApiRequest::patch(PeppolGatewayApiPaths::WEBHOOK, ['webhookId' => $request->webhookId()->value()])
                ->withJsonBody(WebhookRequestMapper::updateBody($request))
                ->forParticipant($participant),
            [WebhookFactory::class, 'fromArray']
        );
    }
}
