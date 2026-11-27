<?php

namespace DreaM1\EfakturaConnector\Core\Webhooks;

use DreaM1\EfakturaConnector\Domain\EFaktura\Webhooks\WebhookEventType;
use DreaM1\EfakturaConnector\Domain\EFaktura\Webhooks\WebhookStatus;

/** Immutable partial update for a webhook, preserving omitted fields separately from explicit nulls. */
final class WebhookUpdate
{
    private bool $urlSpecified;
    private ?WebhookUrl $url;
    private bool $eventsSpecified;
    /** @var WebhookEventType[]|null */
    private ?array $events;
    private bool $statusSpecified;
    private ?WebhookStatus $status;
    private bool $descriptionSpecified;
    private ?string $description;

    /**
     * @param WebhookEventType[]|null $events Normalized event types, or null when not changed.
     */
    private function __construct(
        bool $urlSpecified,
        ?WebhookUrl $url,
        bool $eventsSpecified,
        ?array $events,
        bool $statusSpecified,
        ?WebhookStatus $status,
        bool $descriptionSpecified,
        ?string $description
    ) {
        $this->urlSpecified         = $urlSpecified;
        $this->url                  = $url;
        $this->eventsSpecified      = $eventsSpecified;
        $this->events               = $events === null ? null : WebhookEvents::normalize($events);
        $this->statusSpecified      = $statusSpecified;
        $this->status               = $status;
        $this->descriptionSpecified = $descriptionSpecified;
        $this->description          = $description;
    }

    /** Creates an empty update which can be populated with the copy-returning with* methods. */
    public static function empty(): self
    {
        return new self(false, null, false, null, false, null, false, null);
    }

    /**
     * Returns a copy with the callback URL supplied; null is preserved as an explicit value.
     *
     * @param  WebhookUrl|null $url Replacement callback URL, or null to explicitly clear it.
     * @return self
     */
    public function withUrl(?WebhookUrl $url): self
    {
        return new self(
            true,
            $url,
            $this->eventsSpecified,
            $this->events,
            $this->statusSpecified,
            $this->status,
            $this->descriptionSpecified,
            $this->description
        );
    }

    /**
     * Returns a copy with supported event types; null and an empty list remain distinct.
     *
     * @param  WebhookEventType[]|null $events Complete replacement event list, null to clear it.
     * @return self
     */
    public function withEvents(?array $events): self
    {
        return new self(
            $this->urlSpecified,
            $this->url,
            true,
            $events,
            $this->statusSpecified,
            $this->status,
            $this->descriptionSpecified,
            $this->description
        );
    }

    /**
     * Returns a copy with the status supplied; null is preserved as an explicit value.
     *
     * @param  WebhookStatus|null $status Replacement webhook status, or null to explicitly clear it.
     * @return self
     */
    public function withStatus(?WebhookStatus $status): self
    {
        return new self(
            $this->urlSpecified,
            $this->url,
            $this->eventsSpecified,
            $this->events,
            true,
            $status,
            $this->descriptionSpecified,
            $this->description
        );
    }

    /**
     * Returns a copy with the description supplied; null is preserved as an explicit value.
     *
     * @param  string|null $description Replacement description, or null to explicitly clear it.
     * @return self
     */
    public function withDescription(?string $description): self
    {
        return new self(
            $this->urlSpecified,
            $this->url,
            $this->eventsSpecified,
            $this->events,
            $this->statusSpecified,
            $this->status,
            true,
            $description
        );
    }

    /** Whether the update explicitly supplies a URL, including an explicit null. */
    public function hasUrl(): bool
    {
        return $this->urlSpecified;
    }

    /** Returns the supplied URL value; check hasUrl() to distinguish omission from null. */
    public function url(): ?WebhookUrl
    {
        return $this->url;
    }

    /** Whether the update explicitly supplies an event list, including null. */
    public function hasEvents(): bool
    {
        return $this->eventsSpecified;
    }

    /**
     * Returns the supplied event list; check hasEvents() to distinguish omission from null.
     *
     * @return WebhookEventType[]|null
     */
    public function events(): ?array
    {
        return $this->events;
    }

    /** Whether the update explicitly supplies a status, including an explicit null. */
    public function hasStatus(): bool
    {
        return $this->statusSpecified;
    }

    /** Returns the supplied status; check hasStatus() to distinguish omission from null. */
    public function status(): ?WebhookStatus
    {
        return $this->status;
    }

    /** Whether the update explicitly supplies a description, including null. */
    public function hasDescription(): bool
    {
        return $this->descriptionSpecified;
    }

    /** Returns the supplied description; check hasDescription() to distinguish omission from null. */
    public function description(): ?string
    {
        return $this->description;
    }

    /** Whether none of the webhook fields has been supplied for update. */
    public function isEmpty(): bool
    {
        return ! $this->urlSpecified
            && ! $this->eventsSpecified
            && ! $this->statusSpecified
            && ! $this->descriptionSpecified;
    }
}
