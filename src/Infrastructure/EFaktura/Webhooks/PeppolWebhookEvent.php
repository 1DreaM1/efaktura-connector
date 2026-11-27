<?php

namespace DreaM1\EfakturaConnector\Infrastructure\EFaktura\Webhooks;

use DreaM1\EfakturaConnector\Core\Webhooks\WebhookEventPayload;
use DreaM1\EfakturaConnector\Domain\EFaktura\ReceivedDocumentMetadata;
use DreaM1\EfakturaConnector\Domain\EFaktura\Webhooks\SentDocumentStatusChangedEvent;

/**
 * Parsed Peppol delivery envelope and its event-specific typed payload.
 *
 * @api
 */
final class PeppolWebhookEvent
{
    private WebhookEventPayload $envelope;
    /** @var SentDocumentStatusChangedEvent|ReceivedDocumentMetadata|WebhookEventPayload */
    private object $eventPayload;

    /**
     * Creates a mapped webhook event from its validated envelope and typed data.
     *
     * @param WebhookEventPayload                                                         $envelope     Validated provider event envelope.
     * @param SentDocumentStatusChangedEvent|ReceivedDocumentMetadata|WebhookEventPayload $eventPayload
     *                                                                                                  Typed event data, or the validated envelope for webhook.test.
     */
    public function __construct(WebhookEventPayload $envelope, object $eventPayload)
    {
        $this->envelope     = $envelope;
        $this->eventPayload = $eventPayload;
    }

    /** Returns the validated event envelope. */
    public function envelope(): WebhookEventPayload
    {
        return $this->envelope;
    }

    /**
     * Returns the event-specific typed model.
     *
     * @return SentDocumentStatusChangedEvent|ReceivedDocumentMetadata|WebhookEventPayload
     */
    public function eventPayload(): object
    {
        return $this->eventPayload;
    }
}
