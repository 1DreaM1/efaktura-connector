<?php

namespace DreaM1\EfakturaConnector\Infrastructure\EFaktura\Webhooks;

use DreaM1\EfakturaConnector\Core\Exceptions\InvalidProviderResponseException;
use DreaM1\EfakturaConnector\Core\Exceptions\InvalidWebhookPayloadException;
use DreaM1\EfakturaConnector\Core\Webhooks\WebhookEventPayload;
use DreaM1\EfakturaConnector\Core\Webhooks\WebhookSigningSecret;
use DreaM1\EfakturaConnector\Domain\EFaktura\ReceivedDocumentMetadata;
use DreaM1\EfakturaConnector\Domain\EFaktura\Webhooks\SentDocumentStatusChangedEvent;
use DreaM1\EfakturaConnector\Domain\EFaktura\Webhooks\WebhookEventLimits;
use DreaM1\EfakturaConnector\Domain\EFaktura\Webhooks\WebhookEventType;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Response\ProviderResponseReader;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Response\Sapi\ReceivedDocumentMetadataFactory;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Clock\ClockInterface;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Clock\SystemClock;
use DreaM1\EfakturaConnector\Support\Exceptions\InvalidInputException;
use DreaM1\EfakturaConnector\Support\TypedArray;

/**
 * Verifies and maps one incoming Peppol Gateway webhook delivery.
 *
 * @api
 */
final class PeppolWebhookEventFactory
{
    /** Upper bound on secrets tried for one delivery, keeping verification cost predictable. */
    public const MAX_ROTATION_SECRETS = 5;
    /** Test event sent when a webhook URL is registered or changed. */
    private const WEBHOOK_TEST_EVENT = 'webhook.test';

    private PeppolWebhookSignatureVerifierInterface $signatureVerifier;
    private ?int $timestampToleranceSeconds = null;
    private ClockInterface $clock;

    /**
     * Requires an explicit signature verifier, such as PeppolWebhookHmacSha256SignatureVerifier.
     *
     * @param PeppolWebhookSignatureVerifierInterface $signatureVerifier
     *                                                                   Verifier for the selected signing scheme.
     */
    public function __construct(PeppolWebhookSignatureVerifierInterface $signatureVerifier)
    {
        $this->signatureVerifier = $signatureVerifier;
        $this->clock             = new SystemClock();
    }

    /**
     * Returns a copy that also rejects authenticated deliveries whose signed timestamp is too far from now.
     *
     * The provider does not publish a tolerance, so this check is opt-in and the window is the host's
     * freshness policy. It runs only after the signature has been verified and narrows the replay
     * window; the host must still deduplicate events by delivery ID.
     *
     * @param  int                   $toleranceSeconds Maximum allowed distance between the timestamp and now.
     * @param  ClockInterface|null   $clock            Time source; defaults to the system clock.
     * @throws InvalidInputException when the tolerance is not positive.
     */
    public function withTimestampTolerance(int $toleranceSeconds, ?ClockInterface $clock = null): self
    {
        if ($toleranceSeconds < 1) {
            throw new InvalidInputException('Webhook timestamp tolerance must be positive.');
        }

        $factory                            = clone $this;
        $factory->timestampToleranceSeconds = $toleranceSeconds;
        $factory->clock                     = $clock ?? new SystemClock();

        return $factory;
    }

    /**
     * Verifies the raw delivery before parsing and mapping its event payload.
     *
     * The raw body size is checked before verification. The supplied headers are passed to the verifier
     * as-is; this method does not compare them with fields in the JSON envelope.
     * Supported events are sent_document.status_changed, inbox_document.received and webhook.test; they map
     * to SentDocumentStatusChangedEvent, ReceivedDocumentMetadata and the validated envelope respectively.
     *
     * @param  string                         $rawBody Raw HTTP request body, before JSON decoding.
     * @param  PeppolWebhookHeaders           $headers Required provider delivery headers.
     * @param  WebhookSigningSecret           $secret  Secret selected for this delivery by the caller.
     * @return PeppolWebhookEvent
     * @throws InvalidWebhookPayloadException when verification, the optional timestamp check, envelope
     *                                        validation or event mapping fails, including for an unsupported
     *                                        event type
     */
    public function fromSignedJson(
        string $rawBody,
        PeppolWebhookHeaders $headers,
        WebhookSigningSecret $secret
    ): PeppolWebhookEvent {
        return $this->fromSignedJsonWithAnySecret($rawBody, $headers, [$secret]);
    }

    /**
     * Verifies the raw delivery against any of the supplied secrets, then parses and maps it.
     *
     * Use this while rotating a webhook signing secret: pass the new secret and the previous one until
     * deliveries signed with the previous secret can no longer arrive. Secrets are tried in order and
     * the first valid signature wins; the event is mapped exactly as by fromSignedJson().
     *
     * @param  string                         $rawBody Raw HTTP request body, before JSON decoding.
     * @param  PeppolWebhookHeaders           $headers Required provider delivery headers.
     * @param  WebhookSigningSecret[]         $secrets One to MAX_ROTATION_SECRETS candidate secrets.
     * @return PeppolWebhookEvent
     * @throws InvalidInputException          when the secret list is empty, too long or has other values.
     * @throws InvalidWebhookPayloadException when no secret verifies the delivery, or when the optional
     *                                        timestamp check, envelope validation or event mapping fails
     */
    public function fromSignedJsonWithAnySecret(
        string $rawBody,
        PeppolWebhookHeaders $headers,
        array $secrets
    ): PeppolWebhookEvent {
        $secrets = TypedArray::valuesOfType(
            $secrets,
            WebhookSigningSecret::class,
            'Webhook signing secrets must be WebhookSigningSecret values.'
        );

        if ($secrets === [] || count($secrets) > self::MAX_ROTATION_SECRETS) {
            throw new InvalidInputException(
                'Between one and ' . self::MAX_ROTATION_SECRETS . ' webhook signing secrets are required.'
            );
        }

        if (strlen($rawBody) > WebhookEventLimits::MAX_PAYLOAD_SIZE_BYTES) {
            throw new InvalidWebhookPayloadException('Webhook payload exceeds the maximum allowed size.');
        }

        $this->verifyWithAnySecret($rawBody, $headers, $secrets);
        $this->assertTimestampWithinTolerance($headers);

        $envelope = WebhookEventPayload::fromJson($rawBody);

        return new PeppolWebhookEvent($envelope, self::mapEventPayload($envelope));
    }

    /**
     * Returns after the first secret that verifies the delivery.
     *
     * @param  WebhookSigningSecret[]         $secrets Non-empty candidate secrets.
     * @throws InvalidWebhookPayloadException when every secret is rejected; the last failure is chained.
     */
    private function verifyWithAnySecret(string $rawBody, PeppolWebhookHeaders $headers, array $secrets): void
    {
        $lastFailure = null;

        foreach ($secrets as $secret) {
            try {
                $this->signatureVerifier->verify($rawBody, $headers, $secret);

                return;
            } catch (InvalidWebhookPayloadException $exception) {
                $lastFailure = $exception;
            }
        }

        if (count($secrets) === 1) {
            throw $lastFailure;
        }

        throw new InvalidWebhookPayloadException(
            'Peppol webhook signature is invalid for every supplied signing secret.',
            0,
            $lastFailure
        );
    }

    /**
     * Applies the optional freshness window to the already authenticated delivery timestamp.
     *
     * @throws InvalidWebhookPayloadException when the timestamp is malformed or outside the tolerance.
     */
    private function assertTimestampWithinTolerance(PeppolWebhookHeaders $headers): void
    {
        if ($this->timestampToleranceSeconds === null) {
            return;
        }

        $timestamp = $headers->timestamp();

        // Reject non-decimal and overlong values before integer conversion could saturate.
        if (! ctype_digit($timestamp) || strlen($timestamp) >= strlen((string) PHP_INT_MAX)) {
            throw new InvalidWebhookPayloadException('Peppol webhook timestamp is invalid.');
        }

        if (abs($this->clock->now() - (int) $timestamp) > $this->timestampToleranceSeconds) {
            throw new InvalidWebhookPayloadException('Peppol webhook timestamp is outside the allowed tolerance.');
        }
    }

    /**
     * Maps each currently supported event without exposing its provider data as an array.
     *
     * @return SentDocumentStatusChangedEvent|ReceivedDocumentMetadata|WebhookEventPayload
     * @throws InvalidWebhookPayloadException                                              when the event type or event-specific data is invalid
     */
    private static function mapEventPayload(WebhookEventPayload $envelope): object
    {
        switch ($envelope->eventType()->value()) {
            case WebhookEventType::SENT_DOCUMENT_STATUS_CHANGED:
                return SentDocumentStatusChangedEventFactory::fromArray($envelope->data());

            case WebhookEventType::INBOX_DOCUMENT_RECEIVED:
                try {
                    return ReceivedDocumentMetadataFactory::fromData(
                        new ProviderResponseReader($envelope->data(), 'ReceivedDocumentMetadata')
                    );
                } catch (InvalidProviderResponseException $exception) {
                    throw new InvalidWebhookPayloadException(
                        'Webhook received-document event contains invalid metadata.',
                        0,
                        $exception
                    );
                }

            case self::WEBHOOK_TEST_EVENT:
                return $envelope;

            default:
                throw new InvalidWebhookPayloadException('Webhook event type is not supported.');
        }
    }
}
