<?php

namespace DreaM1\EfakturaConnector\Infrastructure\EFaktura\Webhooks;

use DreaM1\EfakturaConnector\Core\Contracts\WebhookRegistryInterface;
use DreaM1\EfakturaConnector\Core\Exceptions\ProviderErrorResponseException;
use DreaM1\EfakturaConnector\Core\Webhooks\CreateWebhookRequest;
use DreaM1\EfakturaConnector\Core\Webhooks\DeleteWebhookRequest;
use DreaM1\EfakturaConnector\Core\Webhooks\ListWebhooksRequest;
use DreaM1\EfakturaConnector\Core\Webhooks\UpdateWebhookRequest;
use DreaM1\EfakturaConnector\Core\Webhooks\Webhook;
use DreaM1\EfakturaConnector\Core\Webhooks\WebhookRegistrationResponse;
use DreaM1\EfakturaConnector\Core\Webhooks\WebhookRegistryPage;
use DreaM1\EfakturaConnector\Domain\EFaktura\Participant;
use DreaM1\EfakturaConnector\Domain\EFaktura\Webhooks\WebhookIdentifier;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Endpoints\PeppolGateway\DeleteWebhookEndpoint;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Endpoints\PeppolGateway\GetWebhookEndpoint;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Endpoints\PeppolGateway\ListWebhooksEndpoint;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Endpoints\PeppolGateway\RegisterWebhookEndpoint;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Endpoints\PeppolGateway\UpdateWebhookEndpoint;

/** Implements webhook registration, update, listing and deletion via Peppol Gateway endpoints. */
final class PeppolGatewayWebhookRegistry implements WebhookRegistryInterface
{
    private RegisterWebhookEndpoint $registerWebhook;
    private UpdateWebhookEndpoint $updateWebhook;
    private DeleteWebhookEndpoint $deleteWebhook;
    private GetWebhookEndpoint $getWebhook;
    private ListWebhooksEndpoint $listWebhooks;

    /**
     * @param RegisterWebhookEndpoint $registerWebhook Endpoint used to register a webhook subscription.
     * @param UpdateWebhookEndpoint   $updateWebhook   Endpoint used to update a webhook subscription.
     * @param DeleteWebhookEndpoint   $deleteWebhook   Endpoint used to delete a webhook subscription.
     * @param GetWebhookEndpoint      $getWebhook      Endpoint used to retrieve one webhook subscription.
     * @param ListWebhooksEndpoint    $listWebhooks    Endpoint used to list webhook subscriptions.
     */
    public function __construct(
        RegisterWebhookEndpoint $registerWebhook,
        UpdateWebhookEndpoint $updateWebhook,
        DeleteWebhookEndpoint $deleteWebhook,
        GetWebhookEndpoint $getWebhook,
        ListWebhooksEndpoint $listWebhooks
    ) {
        $this->registerWebhook = $registerWebhook;
        $this->updateWebhook   = $updateWebhook;
        $this->deleteWebhook   = $deleteWebhook;
        $this->getWebhook      = $getWebhook;
        $this->listWebhooks    = $listWebhooks;
    }

    /** {@inheritdoc} */
    public function get(Participant $participant, WebhookIdentifier $webhookId): Webhook
    {
        return $this->getWebhook->send($participant, $webhookId);
    }

    /**
     * Registers the requested webhook with the provider.
     *
     * @param  Participant                 $participant Participant whose identity scopes the provider operation.
     * @param  CreateWebhookRequest        $request     Participant, callback URL, event subscriptions and
     *                                                  optional description to register.
     * @return WebhookRegistrationResponse
     *                                     {@inheritdoc}
     */
    public function register(Participant $participant, CreateWebhookRequest $request): WebhookRegistrationResponse
    {
        return $this->registerWebhook->send($participant, $request);
    }

    /**
     * Applies and persists the supplied model attributes.
     *
     * @param  Participant          $participant Participant whose identity scopes the provider operation.
     * @param  UpdateWebhookRequest $request     Participant, webhook ID and fields explicitly supplied for update.
     * @return Webhook
     *                              {@inheritdoc}
     */
    public function update(Participant $participant, UpdateWebhookRequest $request): Webhook
    {
        return $this->updateWebhook->send($participant, $request);
    }

    /**
     * Returns one page of webhook registrations.
     *
     * @param  Participant         $participant Participant whose identity scopes the provider operation.
     * @param  ListWebhooksRequest $request     Participant and optional page cursor and page size.
     * @return WebhookRegistryPage
     *                             {@inheritdoc}
     */
    public function listPage(Participant $participant, ListWebhooksRequest $request): WebhookRegistryPage
    {
        return $this->listWebhooks->send($participant, $request);
    }

    /**
     * Returns false when the webhook no longer exists at the provider.
     *
     * @param  Participant          $participant Participant whose identity scopes the provider operation.
     * @param  DeleteWebhookRequest $request     Participant and webhook ID identifying the registration to delete.
     * @return bool
     *                              {@inheritdoc}
     */
    public function delete(Participant $participant, DeleteWebhookRequest $request): bool
    {
        try {
            $this->deleteWebhook->send($participant, $request->webhookId());
        } catch (ProviderErrorResponseException $exception) {
            if ($exception->httpStatusCode() !== null && $exception->httpStatusCode()->isNotFound()) {
                // Report that no registration existed instead of surfacing an already-absent webhook as an API failure.
                return false;
            }

            throw $exception;
        }

        return true;
    }
}
