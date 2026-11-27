<?php

namespace DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Serialization;

use DreaM1\EfakturaConnector\Core\Webhooks\CreateWebhookRequest;
use DreaM1\EfakturaConnector\Core\Webhooks\UpdateWebhookRequest;
use DreaM1\EfakturaConnector\Core\Webhooks\WebhookEvents;

/**
 * Maps typed webhook requests to the Peppol Gateway payload shape.
 *
 * @internal
 */
final class WebhookRequestMapper
{
    private function __construct()
    {
    }

    /**
     * Builds the WebhookCreateRequest body; an absent description is omitted.
     *
     * @param  CreateWebhookRequest $request Callback URL, event subscriptions and optional description.
     * @return array<string, mixed>
     */
    public static function createBody(CreateWebhookRequest $request): array
    {
        $body = [
            'url'    => $request->url()->value(),
            'events' => WebhookEvents::values($request->events()),
        ];

        if ($request->description() !== null) {
            $body['description'] = $request->description();
        }

        return $body;
    }

    /**
     * Builds the WebhookUpdateRequest body from the fields the caller explicitly supplied.
     *
     * An explicitly supplied null is preserved so the provider can clear that field.
     *
     * @param  UpdateWebhookRequest $request Webhook ID and partial update.
     * @return array<string, mixed>
     */
    public static function updateBody(UpdateWebhookRequest $request): array
    {
        $update = $request->update();
        $body   = [];

        if ($update->hasUrl()) {
            $body['url'] = $update->url() === null ? null : $update->url()->value();
        }

        if ($update->hasEvents()) {
            $body['events'] = $update->events() === null ? null : WebhookEvents::values($update->events());
        }

        if ($update->hasStatus()) {
            $body['status'] = $update->status() === null ? null : $update->status()->value();
        }

        if ($update->hasDescription()) {
            $body['description'] = $update->description();
        }

        return $body;
    }
}
