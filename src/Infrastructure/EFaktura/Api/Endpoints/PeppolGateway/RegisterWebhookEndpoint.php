<?php

namespace DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Endpoints\PeppolGateway;

use DreaM1\EfakturaConnector\Core\Webhooks\CreateWebhookRequest;
use DreaM1\EfakturaConnector\Core\Webhooks\WebhookRegistrationResponse;
use DreaM1\EfakturaConnector\Domain\EFaktura\Participant;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\ApiRequest;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Endpoints\AbstractEndpoint;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Response\PeppolGateway\WebhookRegistrationResponseFactory;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Serialization\WebhookRequestMapper;

/**
 * Registers a webhook and returns the Core registration result.
 * POST /v1/webhooks → 201 WebhookCreateResponse
 */
class RegisterWebhookEndpoint extends AbstractEndpoint
{
    /**
     * Sends the request to the provider endpoint and returns its mapped response.
     *
     * @param  Participant                 $participant Participant that owns the webhook.
     * @param  CreateWebhookRequest        $request     Callback URL and event subscriptions to register.
     * @return WebhookRegistrationResponse
     */
    public function send(Participant $participant, CreateWebhookRequest $request): WebhookRegistrationResponse
    {
        return $this->api->json(
            ApiRequest::post(PeppolGatewayApiPaths::WEBHOOKS)
                ->withJsonBody(WebhookRequestMapper::createBody($request))
                ->forParticipant($participant),
            [WebhookRegistrationResponseFactory::class, 'fromArray']
        );
    }
}
