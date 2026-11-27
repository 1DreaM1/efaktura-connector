<?php

namespace DreaM1\EfakturaConnector\Infrastructure\EFaktura\Webhooks;

use DreaM1\EfakturaConnector\Support\Exceptions\InvalidInputException;
use DreaM1\EfakturaConnector\Support\NonBlankString;

/**
 * Raw provider headers required to process one Peppol Gateway webhook delivery.
 *
 * @api
 */
final class PeppolWebhookHeaders
{
    /** Header that names the delivered event. */
    public const EVENT_HEADER = 'x-sapi-webhook-event';
    /** Header that identifies this webhook delivery. */
    public const ID_HEADER = 'x-sapi-webhook-id';
    /** Header containing the provider's delivery timestamp. */
    public const TIMESTAMP_HEADER = 'x-sapi-webhook-timestamp';
    /** Header containing the provider's delivery signature. */
    public const SIGNATURE_HEADER = 'x-sapi-webhook-signature';

    private string $event;
    private string $id;
    private string $timestamp;
    private string $signature;

    /**
     * @throws InvalidInputException when any supplied header value is blank
     */
    private function __construct(string $event, string $id, string $timestamp, string $signature)
    {
        foreach ([$event, $id, $timestamp, $signature] as $value) {
            if (! NonBlankString::isNotBlank($value)) {
                throw new InvalidInputException('Peppol webhook header values must not be blank.');
            }
        }

        $this->event     = $event;
        $this->id        = $id;
        $this->timestamp = $timestamp;
        $this->signature = $signature;
    }

    /**
     * Redacts the signature header from debug output.
     *
     * @return array{event: string, id: string, timestamp: string, signature: string}
     */
    public function __debugInfo(): array
    {
        return [
            'event'     => $this->event,
            'id'        => $this->id,
            'timestamp' => $this->timestamp,
            'signature' => '[redacted]',
        ];
    }

    /**
     * Creates a header value object from the four provider-supplied header values.
     *
     * @throws InvalidInputException when any supplied header value is blank
     */
    public static function fromValues(string $event, string $id, string $timestamp, string $signature): self
    {
        return new self($event, $id, $timestamp, $signature);
    }

    /** Returns the event name from the delivery headers. */
    public function event(): string
    {
        return $this->event;
    }

    /** Returns the provider's delivery identifier. */
    public function id(): string
    {
        return $this->id;
    }

    /** Returns the provider's delivery timestamp header. */
    public function timestamp(): string
    {
        return $this->timestamp;
    }

    /** Returns the signature header; avoid exposing it in logs. */
    public function signature(): string
    {
        return $this->signature;
    }
}
