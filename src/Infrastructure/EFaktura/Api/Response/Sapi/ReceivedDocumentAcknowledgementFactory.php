<?php

namespace DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Response\Sapi;

use DreaM1\EfakturaConnector\Core\Documents\ReceivedDocumentAcknowledgement;
use DreaM1\EfakturaConnector\Core\Exceptions\InvalidProviderResponseException;
use DreaM1\EfakturaConnector\Domain\EFaktura\ProviderDocumentId;
use DreaM1\EfakturaConnector\Domain\EFaktura\ProviderDocumentStatus;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Response\ProviderResponseReader;

/**
 * Maps POST /sapi/document/receive/{documentId}/acknowledge → 200 SapiAcknowledgeResponse to the public model:
 * { documentId, status, acknowledgedDateTime }
 *
 * The response schema and ReceivedDocumentAcknowledgement carry the same values, so no separate response object
 * is kept.
 */
final class ReceivedDocumentAcknowledgementFactory
{
    private const SCHEMA = 'SapiAcknowledgeResponse';

    private function __construct()
    {
    }

    /**
     * Maps a decoded provider response object to the public acknowledgement.
     *
     * @param  array<array-key, mixed>          $data Decoded provider response object.
     * @return ReceivedDocumentAcknowledgement
     * @throws InvalidProviderResponseException when a required field is missing or invalid.
     */
    public static function fromArray(array $data): ReceivedDocumentAcknowledgement
    {
        return self::fromData(new ProviderResponseReader($data, self::SCHEMA));
    }

    /**
     * Maps validated provider fields to the public acknowledgement.
     *
     * @param  ProviderResponseReader           $response Validated access to the provider fields.
     * @return ReceivedDocumentAcknowledgement
     * @throws InvalidProviderResponseException when a required field is missing or invalid.
     */
    public static function fromData(ProviderResponseReader $response): ReceivedDocumentAcknowledgement
    {
        $acknowledgedDateTime = $response->requiredTimestamp('acknowledgedDateTime');

        return new ReceivedDocumentAcknowledgement(
            ProviderDocumentId::fromString($response->requiredString('documentId')),
            ProviderDocumentStatus::fromProvider($response->requiredString('status')),
            $acknowledgedDateTime
        );
    }
}
