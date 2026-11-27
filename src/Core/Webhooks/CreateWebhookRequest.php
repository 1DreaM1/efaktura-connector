<?php

namespace DreaM1\EfakturaConnector\Core\Webhooks;

use DreaM1\EfakturaConnector\Domain\EFaktura\Webhooks\WebhookEventType;
use DreaM1\EfakturaConnector\Support\Exceptions\InvalidInputException;

/** Input data for registering a webhook callback and its subscriptions. */
final class CreateWebhookRequest
{
    private WebhookUrl $url;
    /** @var WebhookEventType[] */
    private array $events;
    private ?string $description;

    /**
     * Creates a webhook registration request with validated callback URL and events.
     *
     * @param  WebhookUrl            $url         Validated callback URL.
     * @param  WebhookEventType[]    $events      Event types to subscribe to.
     * @param  string|null           $description Human-readable description associated with the value.
     * @throws InvalidInputException when an event name is invalid
     */
    public function __construct(WebhookUrl $url, array $events, ?string $description = null)
    {
        $this->url         = $url;
        $this->events      = WebhookEvents::normalize($events);
        $this->description = $description;
    }

    /**
     * Returns the callback URL that will receive webhook events.
     *
     * @return WebhookUrl
     */
    public function url(): WebhookUrl
    {
        return $this->url;
    }

    /**
     * Returns the normalized event types requested for this webhook.
     *
     * @return WebhookEventType[]
     */
    public function events(): array
    {
        return $this->events;
    }

    /**
     * Returns the optional description attached to the webhook registration.
     *
     * @return string|null
     */
    public function description(): ?string
    {
        return $this->description;
    }
}
