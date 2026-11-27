<?php

namespace DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Response\PeppolGateway;

use DreaM1\EfakturaConnector\Core\Documents\SentDocumentError;
use DreaM1\EfakturaConnector\Core\Documents\SentDocumentIdentity;
use DreaM1\EfakturaConnector\Core\Documents\SentDocumentOutcome;
use DreaM1\EfakturaConnector\Core\Documents\SentDocumentProviderReference;
use DreaM1\EfakturaConnector\Core\Documents\SentDocumentStatus;
use DreaM1\EfakturaConnector\Core\Documents\SentDocumentTiming;
use DreaM1\EfakturaConnector\Core\Exceptions\InvalidProviderResponseException;
use DreaM1\EfakturaConnector\Core\ProviderErrors\ProviderErrorDetail;
use DreaM1\EfakturaConnector\Domain\EFaktura\PeppolDocumentTypeIdentifier;
use DreaM1\EfakturaConnector\Domain\EFaktura\PeppolProcessIdentifier;
use DreaM1\EfakturaConnector\Domain\EFaktura\ProviderDocumentId;
use DreaM1\EfakturaConnector\Domain\EFaktura\ProviderDocumentStatus;
use DreaM1\EfakturaConnector\Domain\EFaktura\ProviderMessageId;
use DreaM1\EfakturaConnector\Domain\EFaktura\SubmittedDocumentId;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Response\DocumentMetadataFields;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Response\ParticipantResponseParser;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Response\ProviderResponseReader;

/**
 * Maps GET /v1/sent-documents/{identifier} → 200 SentDocumentResponse to the public sent-document status.
 *
 * The schema has no required fields: each field is validated when present and omissions stay null. The nested
 * ProviderReference and SentDocumentError objects are mapped straight to their Core models.
 */
final class SentDocumentStatusFactory
{
    private const SCHEMA = 'SentDocumentResponse';

    private function __construct()
    {
    }

    /**
     * Maps a decoded provider response object to the public sent-document status.
     *
     * @param  array<array-key, mixed>          $data Decoded provider response object.
     * @return SentDocumentStatus
     * @throws InvalidProviderResponseException when a supplied field is invalid.
     */
    public static function fromArray(array $data): SentDocumentStatus
    {
        return self::fromData(new ProviderResponseReader($data, self::SCHEMA));
    }

    /**
     * Maps validated provider fields to the public sent-document status.
     *
     * @param  ProviderResponseReader           $response Validated access to the provider fields.
     * @return SentDocumentStatus
     * @throws InvalidProviderResponseException when a supplied field is invalid.
     */
    public static function fromData(ProviderResponseReader $response): SentDocumentStatus
    {
        $provider = $response->optionalObject('provider', 'ProviderReference');
        $error    = $response->optionalObject('error', 'SentDocumentError');

        $senderParticipantId   = $response->optionalStringAllowBlank(DocumentMetadataFields::SENDER_PARTICIPANT_ID);
        $receiverParticipantId = $response->optionalStringAllowBlank(DocumentMetadataFields::RECEIVER_PARTICIPANT_ID);
        $createdAt             = $response->optionalTimestamp('createdAt');
        $updatedAt             = $response->optionalTimestamp('updatedAt');

        $identifier     = $response->optionalNonBlankString('identifier');
        $status         = $response->optionalString('status');
        $documentId     = $response->optionalNonBlankString(DocumentMetadataFields::DOCUMENT_ID);
        $documentTypeId = $response->optionalNonBlankString(DocumentMetadataFields::DOCUMENT_TYPE_ID);
        $processId      = $response->optionalNonBlankString(DocumentMetadataFields::PROCESS_ID);

        return SentDocumentStatus::create(
            $status !== null ? ProviderDocumentStatus::fromProvider($status) : null,
            // Absent participant fields stay null; malformed identifiers that were supplied become response errors.
            ParticipantResponseParser::parseParties($senderParticipantId, $receiverParticipantId, self::SCHEMA),
            new SentDocumentTiming($createdAt, $updatedAt),
            new SentDocumentIdentity(
                $identifier !== null ? ProviderDocumentId::fromString($identifier) : null,
                $documentId !== null ? SubmittedDocumentId::fromString($documentId) : null,
                $documentTypeId !== null ? PeppolDocumentTypeIdentifier::fromString($documentTypeId) : null,
                $processId !== null ? PeppolProcessIdentifier::fromString($processId) : null,
                $response->optionalString('payloadFormat')
            ),
            new SentDocumentOutcome(
                $provider !== null ? self::providerReference($provider) : null,
                $error !== null ? self::error($error) : null
            )
        );
    }

    private static function providerReference(ProviderResponseReader $data): SentDocumentProviderReference
    {
        $documentId = $data->optionalNonBlankString('documentId');
        $messageId  = $data->optionalNonBlankString('messageId');

        return new SentDocumentProviderReference(
            $data->optionalString('name'),
            $documentId !== null ? ProviderDocumentId::fromString($documentId) : null,
            $messageId !== null ? ProviderMessageId::fromString($messageId) : null
        );
    }

    private static function error(ProviderResponseReader $data): SentDocumentError
    {
        $message = $data->optionalString('message');
        $details = [];

        foreach ($data->optionalObjectList('details', 'SapiErrorDetail') ?? [] as $detail) {
            $details[] = ProviderErrorDetail::fromArray([
                'field' => $detail->optionalString('field'),
                'issue' => $detail->optionalString('issue'),
                'value' => $detail->optionalString('value'),
            ]);
        }

        return new SentDocumentError($message, $details);
    }
}
