<?php

namespace DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Response\Sapi;

use DreaM1\EfakturaConnector\Core\Documents\ReceivedDocumentPayload;
use DreaM1\EfakturaConnector\Core\Exceptions\InvalidProviderResponseException;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Response\DocumentMetadataFields;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Response\ProviderResponseReader;

/**
 * Maps GET /sapi/document/receive/{documentId} → 200 SapiReceivedDocumentDetailResponse to the public payload:
 * { metadata: DocumentMetadata, payload, payloadFormat }
 *
 * The response schema and ReceivedDocumentPayload carry the same values, so no separate response object is kept.
 */
final class ReceivedDocumentPayloadFactory
{
    private const SCHEMA = 'SapiReceivedDocumentDetailResponse';

    private function __construct()
    {
    }

    /**
     * Maps a decoded provider response object to the public received-document payload.
     *
     * @param  array<array-key, mixed>          $data Decoded provider response object.
     * @return ReceivedDocumentPayload
     * @throws InvalidProviderResponseException when a required field is missing or invalid.
     */
    public static function fromArray(array $data): ReceivedDocumentPayload
    {
        return self::fromData(new ProviderResponseReader($data, self::SCHEMA));
    }

    /**
     * Maps validated provider fields to the public received-document payload.
     *
     * @param  ProviderResponseReader           $response Validated access to the provider fields.
     * @return ReceivedDocumentPayload
     * @throws InvalidProviderResponseException when a required field is missing or invalid.
     */
    public static function fromData(ProviderResponseReader $response): ReceivedDocumentPayload
    {
        // Metadata is mapped to domain routing values; payload text and provider format are preserved as received.
        $metadata = ReceivedDocumentMetadataFactory::fromData(
            $response->requiredObject('metadata', DocumentMetadataFields::OBJECT_NAME)
        );

        return new ReceivedDocumentPayload(
            $response->requiredString('payload'),
            $response->requiredString('payloadFormat'),
            $metadata
        );
    }
}
