<?php

namespace DreaM1\EfakturaConnector\Core\Webhooks;

use DreaM1\EfakturaConnector\Domain\EFaktura\Webhooks\WebhookEventType;
use DreaM1\EfakturaConnector\Domain\EFaktura\Webhooks\WebhookIdentifier;
use DreaM1\EfakturaConnector\Domain\EFaktura\Webhooks\WebhookStatus;
use DreaM1\EfakturaConnector\Support\Exceptions\InvalidInputException;
use DreaM1\EfakturaConnector\Support\Timestamp;
use DreaM1\EfakturaConnector\Support\TypedArray;

/** Result of registering a webhook, including its signing secret. */
final class WebhookRegistrationResponse
{
    private WebhookIdentifier $id;
    private WebhookSigningSecret $signingSecret;
    private WebhookUrl $url;
    private ?string $description;
    /** @var WebhookEventType[] Normalized event types returned by the provider. */
    private array $events;
    private WebhookStatus $status;
    private Timestamp $createdAt;
    private Timestamp $updatedAt;

    /**
     * Creates the registration result with the provider's complete response fields.
     * @param  WebhookIdentifier     $id            Provider-assigned webhook identifier.
     * @param  WebhookSigningSecret  $signingSecret Webhook signing secret returned by the provider.
     * @param  WebhookEventType[]    $events        Event subscriptions returned by the provider; empty is allowed
     * @param  WebhookStatus         $status        Provider-reported or domain status value.
     * @param  Timestamp             $createdAt     Creation timestamp reported by the provider.
     * @param  Timestamp             $updatedAt     Timestamp when the provider last updated the resource.
     * @param  WebhookUrl            $url           Validated callback URL.
     * @param  string|null           $description   Human-readable description associated with the value.
     * @throws InvalidInputException when an event name is invalid
     */
    public function __construct(
        WebhookIdentifier $id,
        WebhookSigningSecret $signingSecret,
        array $events,
        WebhookStatus $status,
        Timestamp $createdAt,
        Timestamp $updatedAt,
        WebhookUrl $url,
        ?string $description = null
    ) {
        $this->id            = $id;
        $this->signingSecret = $signingSecret;
        $this->url           = $url;
        $this->description   = $description;
        $this->events        = TypedArray::valuesOfType(
            $events,
            WebhookEventType::class,
            'Webhook event value is invalid.'
        );
        $this->status    = $status;
        $this->createdAt = $createdAt;
        $this->updatedAt = $updatedAt;
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
     * Returns the signing secret.
     *
     * @return WebhookSigningSecret
     */
    public function signingSecret(): WebhookSigningSecret
    {
        return $this->signingSecret;
    }

    /** Returns the callback URL reported by the provider. */
    public function url(): WebhookUrl
    {
        return $this->url;
    }

    /** Returns the optional description reported by the provider. */
    public function description(): ?string
    {
        return $this->description;
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
     * Returns the provider's creation timestamp.
     *
     * @return Timestamp
     */
    public function createdAt(): Timestamp
    {
        return $this->createdAt;
    }

    /**
     * Returns the provider's last-update timestamp.
     *
     * @return Timestamp
     */
    public function updatedAt(): Timestamp
    {
        return $this->updatedAt;
    }
}
