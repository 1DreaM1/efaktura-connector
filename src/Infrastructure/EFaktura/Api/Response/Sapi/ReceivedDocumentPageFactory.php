<?php

namespace DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Response\Sapi;

use DreaM1\EfakturaConnector\Core\Documents\ReceivedDocumentPage;
use DreaM1\EfakturaConnector\Core\Exceptions\InvalidProviderResponseException;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Response\DocumentMetadataFields;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Response\ProviderResponseReader;

/**
 * Maps GET /sapi/document/receive → 200 SapiReceivedDocumentListResponse to the public page model:
 * { documents: DocumentMetadata[], nextPageToken? }
 *
 * The response schema and ReceivedDocumentPage carry the same values, so no separate response object is kept.
 */
final class ReceivedDocumentPageFactory
{
    private const SCHEMA = 'SapiReceivedDocumentListResponse';

    private function __construct()
    {
    }

    /**
     * Maps a decoded provider response object to the public received-document page.
     *
     * @param  array<array-key, mixed>          $data Decoded provider response object.
     * @return ReceivedDocumentPage
     * @throws InvalidProviderResponseException when a required field is missing or invalid.
     */
    public static function fromArray(array $data): ReceivedDocumentPage
    {
        return self::fromData(new ProviderResponseReader($data, self::SCHEMA));
    }

    /**
     * Maps validated provider fields to the public received-document page.
     *
     * @param  ProviderResponseReader           $response Validated access to the provider fields.
     * @return ReceivedDocumentPage
     * @throws InvalidProviderResponseException when a required field is missing or invalid.
     */
    public static function fromData(ProviderResponseReader $response): ReceivedDocumentPage
    {
        // Validate each metadata object through one shared factory, then keep the page cursor separate.
        $documents = array_map(
            [ReceivedDocumentMetadataFactory::class, 'fromData'],
            $response->requiredObjectList('documents', DocumentMetadataFields::OBJECT_NAME)
        );
        return new ReceivedDocumentPage($documents, $response->optionalPageToken('nextPageToken'));
    }
}
