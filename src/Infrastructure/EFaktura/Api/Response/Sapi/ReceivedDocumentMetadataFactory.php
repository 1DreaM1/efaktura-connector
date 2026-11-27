<?php

namespace DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Response\Sapi;

use DreaM1\EfakturaConnector\Core\Exceptions\InvalidProviderResponseException;
use DreaM1\EfakturaConnector\Domain\EFaktura\PeppolDocumentTypeIdentifier;
use DreaM1\EfakturaConnector\Domain\EFaktura\PeppolProcessIdentifier;
use DreaM1\EfakturaConnector\Domain\EFaktura\ProviderDocumentId;
use DreaM1\EfakturaConnector\Domain\EFaktura\ReceivedDocumentMetadata;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Response\DocumentMetadataFields;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Response\ParticipantResponseParser;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Response\ProviderResponseReader;

/** Creates the domain routing value from a provider DocumentMetadata object. */
final class ReceivedDocumentMetadataFactory
{
    /** OpenAPI schema name used in provider response diagnostics. */
    public const SCHEMA = DocumentMetadataFields::OBJECT_NAME;

    /**
     * Maps validated provider response fields to received-document metadata.
     *
     * @param  ProviderResponseReader           $data Strict reader for the SAPI document metadata object.
     * @return ReceivedDocumentMetadata
     * @throws InvalidProviderResponseException when required metadata or participant identifiers are invalid.
     */
    public static function fromData(ProviderResponseReader $data): ReceivedDocumentMetadata
    {
        // Read routing fields before constructing the domain value; incomplete metadata cannot identify
        // a usable inbox item.
        $documentId            = $data->requiredString(DocumentMetadataFields::DOCUMENT_ID);
        $senderParticipantId   = $data->requiredString(DocumentMetadataFields::SENDER_PARTICIPANT_ID);
        $receiverParticipantId = $data->requiredString(DocumentMetadataFields::RECEIVER_PARTICIPANT_ID);
        $documentTypeId        = $data->requiredString(DocumentMetadataFields::DOCUMENT_TYPE_ID);
        $processId             = $data->requiredString(DocumentMetadataFields::PROCESS_ID);
        $creationDateTime      = $data->requiredTimestamp(DocumentMetadataFields::CREATION_DATE_TIME);

        return ReceivedDocumentMetadata::create(
            ProviderDocumentId::fromString($documentId),
            ParticipantResponseParser::parse(
                $senderParticipantId,
                self::SCHEMA,
                DocumentMetadataFields::SENDER_PARTICIPANT_ID
            ),
            ParticipantResponseParser::parse(
                $receiverParticipantId,
                self::SCHEMA,
                DocumentMetadataFields::RECEIVER_PARTICIPANT_ID
            ),
            PeppolDocumentTypeIdentifier::fromString($documentTypeId),
            PeppolProcessIdentifier::fromString($processId),
            $creationDateTime
        );
    }
}
