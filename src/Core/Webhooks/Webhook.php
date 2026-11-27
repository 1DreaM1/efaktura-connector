<?php

namespace DreaM1\EfakturaConnector\Core\Webhooks;

use DreaM1\EfakturaConnector\Domain\EFaktura\Webhooks\WebhookEventType;
use DreaM1\EfakturaConnector\Domain\EFaktura\Webhooks\WebhookIdentifier;
use DreaM1\EfakturaConnector\Domain\EFaktura\Webhooks\WebhookStatus;
use DreaM1\EfakturaConnector\Support\Exceptions\InvalidInputException;
use DreaM1\EfakturaConnector\Support\Timestamp;
use DreaM1\EfakturaConnector\Support\TypedArray;

/** Registered callback URL and event subscriptions returned by the provider. */
final class Webhook
{
    private WebhookIdentifier $id;
    private WebhookUrl $url;
    /** @var WebhookEventType[] Normalized event types returned by the provider. */
    private array $events;
    private WebhookStatus $status;
    private ?string $description;
    private Timestamp $createdAt;
    private Timestamp $updatedAt;

    /**
     * Creates a webhook value from validated identity and status objects.
     * @param  WebhookIdentifier     $id          Provider-assigned webhook identifier.
     * @param  WebhookUrl            $url         Validated callback URL.
     * @param  WebhookEventType[]    $events      Provider-reported event types; empty is allowed
     * @param  WebhookStatus         $status      Provider-reported or domain status value.
     * @param  string|null           $description Human-readable description associated with the value.
     * @param  Timestamp             $createdAt   Creation timestamp reported by the provider.
     * @param  Timestamp             $updatedAt   Timestamp when the provider last updated the resource.
     * @throws InvalidInputException when an event is invalid
     */
    public function __construct(
        WebhookIdentifier $id,
        WebhookUrl $url,
        array $events,
        WebhookStatus $status,
        ?string $description,
        Timestamp $createdAt,
        Timestamp $updatedAt
    ) {
        $this->id     = $id;
        $this->url    = $url;
        $this->events = TypedArray::valuesOfType(
            $events,
            WebhookEventType::class,
            'Webhook event value is invalid.'
        );
        $this->status      = $status;
        $this->description = $description;
        $this->createdAt   = $createdAt;
        $this->updatedAt   = $updatedAt;
    }

    /**
     * Returns the ID.
     *
     * @return WebhookIdentifier
     */
    public function id(): WebhookIdentifier
    {
        return $this->id;
    }

    /**
     * Returns the URL.
     *
     * @return WebhookUrl
     */
    public function url(): WebhookUrl
    {
        return $this->url;
    }

    /**
     * Returns the events.
     * @return WebhookEventType[] Normalized provider-reported event types.
     */
    public function events(): array
    {
        return $this->events;
    }

    /**
     * Returns the status.
     *
     * @return WebhookStatus
     */
    public function status(): WebhookStatus
    {
        return $this->status;
    }

    /**
     * Returns the description.
     *
     * @return string|null
     */
    public function description(): ?string
    {
        return $this->description;
    }

    /**
     * Returns the provider's creation timestamp.
     */
    public function createdAt(): Timestamp
    {
        return $this->createdAt;
    }

    /**
     * Returns the provider's last-update timestamp.
     */
    public function updatedAt(): Timestamp
    {
        return $this->updatedAt;
    }

    /**
     * Returns the value as an array for serialization or storage.
     * @return array{id: string, url: string, events: string[], status: string, description: ?string, createdAt: string, updatedAt: string}
     */
    public function toArray(): array
    {
        return [
            'id'          => $this->id->value(),
            'url'         => $this->url->value(),
            'events'      => WebhookEvents::values($this->events),
            'status'      => $this->status->value(),
            'description' => $this->description,
            'createdAt'   => $this->createdAt->value(),
            'updatedAt'   => $this->updatedAt->value(),
        ];
    }
}
