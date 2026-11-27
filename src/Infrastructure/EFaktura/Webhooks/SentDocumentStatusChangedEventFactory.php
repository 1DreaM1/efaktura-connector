<?php

namespace DreaM1\EfakturaConnector\Infrastructure\EFaktura\Webhooks;

use DreaM1\EfakturaConnector\Core\Exceptions\InvalidProviderResponseException;
use DreaM1\EfakturaConnector\Core\Exceptions\InvalidWebhookPayloadException;
use DreaM1\EfakturaConnector\Domain\EFaktura\Participant;
use DreaM1\EfakturaConnector\Domain\EFaktura\ProviderDocumentId;
use DreaM1\EfakturaConnector\Domain\EFaktura\ProviderDocumentStatus;
use DreaM1\EfakturaConnector\Domain\EFaktura\Webhooks\SentDocumentStatusChangedEvent;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Response\ProviderResponseReader;
use DreaM1\EfakturaConnector\Support\Exceptions\InvalidInputException;

/**
 * Builds a domain status-change event from a provider webhook payload.
 *
 * @api
 */
final class SentDocumentStatusChangedEventFactory
{
    /**
     * Validates webhook fields and creates a document status-change event.
     *
     * @param  array<string, mixed>           $data Decoded event object with non-blank identifier, documentId,
     *                                              senderParticipantId, receiverParticipantId and supported status fields; optional non-null error data is
     *                                              reduced to a presence flag without exposing its details.
     * @return SentDocumentStatusChangedEvent
     * @throws InvalidWebhookPayloadException when the payload does not contain a supported status change
     */
    public static function fromArray(array $data): SentDocumentStatusChangedEvent
    {
        return self::fromData(new ProviderResponseReader($data, 'SentDocumentStatusChangedEvent'));
    }

    /**
     * Creates a value from validated provider response data.
     *
     * @param  ProviderResponseReader         $response Provider response value to map or validate.
     * @return SentDocumentStatusChangedEvent
     * @throws InvalidWebhookPayloadException when the provider response is invalid
     */
    private static function fromData(ProviderResponseReader $response): SentDocumentStatusChangedEvent
    {
        // First validate required provider fields and the outbound-only status vocabulary.
        try {
            $identifier            = $response->requiredString('identifier');
            $documentId            = ProviderDocumentId::fromString($response->requiredString('documentId'));
            $senderParticipantId   = $response->requiredString('senderParticipantId');
            $receiverParticipantId = $response->requiredString('receiverParticipantId');
            $status                = ProviderDocumentStatus::fromProvider($response->requiredString('status'));
        } catch (InvalidProviderResponseException $exception) {
            throw new InvalidWebhookPayloadException(
                'Webhook status-change event contains invalid metadata.',
                0,
                $exception
            );
        }

        try {
            // Participant parsing and domain creation reject malformed IDs and unsupported outbound statuses.
            $sender   = Participant::parse($senderParticipantId);
            $receiver = Participant::parse($receiverParticipantId);
            $data     = $response->toArray();
            $hasError = isset($data['error']);

            return SentDocumentStatusChangedEvent::create(
                $identifier,
                $documentId,
                $sender,
                $receiver,
                $status,
                // The event exposes only whether an error exists, not the provider's potentially sensitive details.
                $hasError
            );
        } catch (InvalidInputException $exception) {
            throw new InvalidWebhookPayloadException(
                'Webhook status-change event contains invalid participant or document status data.',
                0,
                $exception
            );
        }
    }
}
