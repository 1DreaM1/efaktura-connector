<?php

namespace DreaM1\EfakturaConnector\Core\Webhooks;

use DreaM1\EfakturaConnector\Core\Exceptions\InvalidWebhookPayloadException;
use DreaM1\EfakturaConnector\Domain\EFaktura\Webhooks\WebhookEventId;
use DreaM1\EfakturaConnector\Domain\EFaktura\Webhooks\WebhookEventLimits;
use DreaM1\EfakturaConnector\Domain\EFaktura\Webhooks\WebhookEventType;
use DreaM1\EfakturaConnector\Support\JsonArrayDecoder;
use DreaM1\EfakturaConnector\Support\NonBlankString;
use DreaM1\EfakturaConnector\Support\Timestamp;
use Exception;
use stdClass;

/**
 * Validated envelope for an incoming provider webhook and its event data.
 *
 * This validates the payload shape and timestamp only. It does not verify a signing secret or
 * authenticate an HTTP delivery. Callers that parse an HTTP delivery directly must verify its
 * signature before trusting this object; the Peppol webhook event factory can perform that step
 * using an injected signature verifier before calling this parser.
 */
final class WebhookEventPayload
{
    private WebhookEventId $eventId;
    private WebhookEventType $eventType;
    private Timestamp $occurredAt;
    /** @var array<array-key, mixed> Event-specific values left for the host's handler to interpret. */
    private array $data;

    /**
     * Restricts construction to the validated webhook payload factory.
     *
     * @param array<array-key, mixed> $data Event-specific values left for the host's handler to interpret.
     */
    private function __construct(
        WebhookEventId $eventId,
        WebhookEventType $eventType,
        Timestamp $occurredAt,
        array $data
    ) {
        $this->eventId    = $eventId;
        $this->eventType  = $eventType;
        $this->occurredAt = $occurredAt;
        $this->data       = $data;
    }

    /**
     * Parses a JSON webhook envelope and validates its required fields.
     *
     * @param  string                         $json JSON text to decode.
     * @return self
     * @throws InvalidWebhookPayloadException when the JSON is invalid, too large or has an invalid envelope
     */
    public static function fromJson(string $json): self
    {
        // Enforce the byte limit before JSON decoding allocates the payload structure.
        if (strlen($json) > WebhookEventLimits::MAX_PAYLOAD_SIZE_BYTES) {
            throw new InvalidWebhookPayloadException('Webhook payload exceeds the maximum allowed size.');
        }

        $payload = JsonArrayDecoder::decodeObject($json);

        // Reject invalid JSON and scalar roots here; missing envelope fields are rejected by fromArray().
        if ($payload === null) {
            throw new InvalidWebhookPayloadException('Webhook payload must be a JSON object.');
        }

        // JSON decoding in associative mode cannot distinguish an event-data object from a list.
        if (array_key_exists('data', $payload) && ! $payload['data'] instanceof stdClass) {
            throw new InvalidWebhookPayloadException('Webhook payload must contain a JSON object "data" field.');
        }

        return self::fromArray(self::normalizeJsonObjects($payload));
    }

    /**
     * Validates an already-decoded envelope while leaving event-specific data to the caller.
     * Apply the raw request size limit before decoding when using this factory for HTTP input.
     *
     * @param  array<string, mixed>           $payload Decoded webhook envelope with event-specific data.
     * @return self
     * @throws InvalidWebhookPayloadException when required fields or the event timestamp are invalid
     */
    public static function fromArray(array $payload): self
    {
        // Validate only the shared envelope; event-specific data is interpreted by its own factory.
        $eventId   = self::requiredString($payload['id'] ?? null, 'id');
        $eventType = self::requiredString($payload['event'] ?? null, 'event');
        $rawDate   = self::requiredString($payload['occurredAt'] ?? null, 'occurredAt');

        $data = $payload['data'] ?? null;

        if (! is_array($data)) {
            throw new InvalidWebhookPayloadException('Webhook payload must contain a "data" field.');
        }

        // Normalize the provider timestamp once so consumers receive a typed RFC 3339 value.
        try {
            $occurredAt = Timestamp::tryParseRfc3339($rawDate);
        } catch (Exception $exception) {
            // Only the documented DateTime construction failure; programming errors must not be reported as bad input.
            throw new InvalidWebhookPayloadException(
                'Webhook payload contains an invalid occurredAt date.',
                0,
                $exception
            );
        }

        if ($occurredAt === null) {
            throw new InvalidWebhookPayloadException('Webhook payload contains an invalid occurredAt date.');
        }

        // Keep event data intact for the handler-specific parser.
        return new self(
            WebhookEventId::fromString($eventId),
            WebhookEventType::fromString($eventType),
            $occurredAt,
            $data
        );
    }

    /**
     * Returns the event ID.
     *
     * @return WebhookEventId
     */
    public function eventId(): WebhookEventId
    {
        return $this->eventId;
    }

    /**
     * Returns the event type.
     *
     * @return WebhookEventType
     */
    public function eventType(): WebhookEventType
    {
        return $this->eventType;
    }

    /**
     * Returns the time at which the provider reports that this event occurred.
     *
     * @return Timestamp
     */
    public function occurredAt(): Timestamp
    {
        return $this->occurredAt;
    }

    /**
     * Returns the data.
     * @return array<array-key, mixed> Event-specific data, interpreted by the consumer.
     */
    public function data(): array
    {
        return $this->data;
    }

    /**
     * Validates one required non-blank string in the webhook envelope.
     * @param mixed $value Decoded value for the required envelope field.
     */
    private static function requiredString($value, string $field): string
    {
        if (! is_string($value) || ! NonBlankString::isNotBlank($value)) {
            throw new InvalidWebhookPayloadException(
                'Webhook payload must contain a string "' . $field . '" field.'
            );
        }

        return $value;
    }

    /**
     * Converts decoded JSON objects to arrays after the envelope object fields have been validated.
     *
     * @param  mixed $value Decoded JSON value.
     * @return mixed
     */
    private static function normalizeJsonObjects($value)
    {
        if ($value instanceof stdClass) {
            $value = get_object_vars($value);
        }

        if (is_array($value)) {
            foreach ($value as $key => $item) {
                $value[$key] = self::normalizeJsonObjects($item);
            }
        }

        return $value;
    }
}
