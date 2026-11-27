<?php

namespace DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Serialization;

use DreaM1\EfakturaConnector\Core\Documents\OutboundDocumentPayload;
use DreaM1\EfakturaConnector\Core\Xml\XmlEncoding;
use DreaM1\EfakturaConnector\Core\Xml\XmlPayloadFormat;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Response\DocumentMetadataFields;

/**
 * Shared JSON body schema for SAPI submission and Gateway validation:
 * { metadata: DocumentMetadata, payload, payloadFormat: "XML", payloadEncoding, checksum? }
 */
final class DocumentSubmissionRequestMapper
{
    /** Payload format label used for XML documents. */
    public const PAYLOAD_FORMAT_XML = XmlPayloadFormat::XML;
    /** Payload encoding used for submitted XML documents. */
    public const PAYLOAD_ENCODING_UTF8 = XmlEncoding::UTF_8;

    /** Prevents instantiation of this static request-body schema. */
    private function __construct()
    {
    }

    /**
     * Maps the outbound payload to the JSON shape used by SAPI submission and Gateway validation.
     * The optional checksum field is included only when the caller supplied one.
     *
     * @param  OutboundDocumentPayload $payload Outbound document metadata and XML to map to the provider request body.
     * @return array<string, mixed>
     */
    public static function fromPayload(OutboundDocumentPayload $payload): array
    {
        // Metadata describes the XML body below; the checksum remains optional for callers that omit it.
        $body = [
            'metadata' => [
                DocumentMetadataFields::DOCUMENT_ID             => $payload->documentId()->value(),
                DocumentMetadataFields::DOCUMENT_TYPE_ID        => $payload->documentTypeId()->value(),
                DocumentMetadataFields::PROCESS_ID              => $payload->processId()->value(),
                DocumentMetadataFields::SENDER_PARTICIPANT_ID   => $payload->sender()->identifier(),
                DocumentMetadataFields::RECEIVER_PARTICIPANT_ID => $payload->receiver()->identifier(),
                DocumentMetadataFields::CREATION_DATE_TIME      => $payload->creationDateTime()->value(),
            ],
            'payload'         => $payload->xml(),
            'payloadFormat'   => self::PAYLOAD_FORMAT_XML,
            'payloadEncoding' => self::PAYLOAD_ENCODING_UTF8,
        ];

        $checksum = $payload->checksum();

        if ($checksum !== null) {
            $body['checksum'] = $checksum->value();
        }

        return $body;
    }
}
