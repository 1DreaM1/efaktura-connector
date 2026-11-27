<?php

namespace DreaM1\EfakturaConnector\Core\Contracts;

use DreaM1\EfakturaConnector\Core\Exceptions\ProviderApiException;
use DreaM1\EfakturaConnector\Core\Exceptions\RequestCoordinationException;
use DreaM1\EfakturaConnector\Core\Exceptions\RequestEncodingException;
use DreaM1\EfakturaConnector\Core\Webhooks\CreateWebhookRequest;
use DreaM1\EfakturaConnector\Core\Webhooks\DeleteWebhookRequest;
use DreaM1\EfakturaConnector\Core\Webhooks\ListWebhooksRequest;
use DreaM1\EfakturaConnector\Core\Webhooks\UpdateWebhookRequest;
use DreaM1\EfakturaConnector\Core\Webhooks\Webhook;
use DreaM1\EfakturaConnector\Core\Webhooks\WebhookRegistrationResponse;
use DreaM1\EfakturaConnector\Core\Webhooks\WebhookRegistryPage;
use DreaM1\EfakturaConnector\Domain\EFaktura\Participant;
use DreaM1\EfakturaConnector\Domain\EFaktura\Webhooks\WebhookIdentifier;

/** Manages webhook subscriptions registered for a Peppol participant. */
interface WebhookRegistryInterface
{
    /**
     * Retrieves one webhook subscription belonging to the participant.
     *
     * @param  Participant                  $participant Participant that owns the webhook subscription.
     * @param  WebhookIdentifier            $webhookId   Provider-assigned webhook identifier.
     * @return Webhook
     * @throws ProviderApiException         when the provider request fails or its response is invalid
     * @throws RequestCoordinationException when local HTTP request coordination cannot be established
     */
    public function get(Participant $participant, WebhookIdentifier $webhookId): Webhook;

    /**
     * Registers a callback URL and its subscribed event types.
     *
     * @param  Participant                  $participant Participant that owns the webhook subscription.
     * @param  CreateWebhookRequest         $request     Callback URL, description and subscribed events.
     * @return WebhookRegistrationResponse
     * @throws ProviderApiException         when the provider request fails or its response is invalid
     * @throws RequestEncodingException     when the registration request body cannot be encoded
     * @throws RequestCoordinationException when local HTTP request coordination cannot be established
     */
    public function register(Participant $participant, CreateWebhookRequest $request): WebhookRegistrationResponse;

    /**
     * Updates the callback URL or event subscriptions of an existing webhook.
     *
     * @param  Participant                  $participant Participant that owns the webhook subscription.
     * @param  UpdateWebhookRequest         $request     Webhook identifier and replacement subscription settings.
     * @return Webhook
     * @throws ProviderApiException         when the provider request fails or its response is invalid
     * @throws RequestEncodingException     when the update request body cannot be encoded
     * @throws RequestCoordinationException when local HTTP request coordination cannot be established
     */
    public function update(Participant $participant, UpdateWebhookRequest $request): Webhook;

    /**
     * Deletes a webhook; returns whether the provider confirmed the deletion.
     *
     * @param  Participant                  $participant Participant that owns the webhook subscription.
     * @param  DeleteWebhookRequest         $request     Identifier of the webhook to delete.
     * @return bool
     * @throws ProviderApiException         when deletion fails for a reason other than not found
     * @throws RequestCoordinationException when local HTTP request coordination cannot be established
     */
    public function delete(Participant $participant, DeleteWebhookRequest $request): bool;

    /**
     * Lists a page of webhook subscriptions for the participant.
     *
     * @param  Participant                  $participant Participant whose webhook subscriptions are listed.
     * @param  ListWebhooksRequest          $request     Pagination options for the requested page.
     * @return WebhookRegistryPage
     * @throws ProviderApiException         when the provider request fails or its response is invalid
     * @throws RequestCoordinationException when local HTTP request coordination cannot be established
     */
    public function listPage(Participant $participant, ListWebhooksRequest $request): WebhookRegistryPage;
}
