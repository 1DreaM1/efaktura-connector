<?php

namespace DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Response\Sapi;

use DreaM1\EfakturaConnector\Core\Documents\DocumentSubmissionResult;
use DreaM1\EfakturaConnector\Core\Exceptions\InvalidProviderResponseException;
use DreaM1\EfakturaConnector\Domain\EFaktura\ProviderDocumentId;
use DreaM1\EfakturaConnector\Domain\EFaktura\ProviderDocumentStatus;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Response\ProviderResponseReader;

/**
 * Maps POST /sapi/document/send → 202 SapiSendDocumentResponse to the public submission result:
 * { providerDocumentId, status, receivedAt?, timestamp }
 *
 * The response schema and DocumentSubmissionResult carry the same values, so no separate response object is kept.
 * Unknown provider statuses are preserved. Only a known ACCEPTED status requires receivedAt; unknown statuses are
 * neither classified as accepted nor rejected.
 */
final class DocumentSubmissionResultFactory
{
    private const SCHEMA = 'SapiSendDocumentResponse';

    private function __construct()
    {
    }

    /**
     * Maps a decoded provider response object to the public submission result.
     *
     * @param  array<array-key, mixed>          $data Decoded provider response object.
     * @return DocumentSubmissionResult
     * @throws InvalidProviderResponseException when a required field is missing or invalid, or a known ACCEPTED
     *                                          response omits receivedAt.
     */
    public static function fromArray(array $data): DocumentSubmissionResult
    {
        return self::fromData(new ProviderResponseReader($data, self::SCHEMA));
    }

    /**
     * Maps validated provider fields to the public submission result.
     *
     * @param  ProviderResponseReader           $response Validated access to the provider fields.
     * @return DocumentSubmissionResult
     * @throws InvalidProviderResponseException when a required field is missing or invalid, or a known ACCEPTED
     *                                          response omits receivedAt.
     */
    public static function fromData(ProviderResponseReader $response): DocumentSubmissionResult
    {
        $providerDocumentId = ProviderDocumentId::fromString($response->requiredString('providerDocumentId'));
        $status             = ProviderDocumentStatus::fromProvider($response->requiredString('status'));
        $timestamp          = $response->requiredTimestamp('timestamp');
        $receivedAt         = $response->optionalTimestamp('receivedAt');

        if ($status->value() === ProviderDocumentStatus::ACCEPTED && $receivedAt === null) {
            throw new InvalidProviderResponseException(
                'Provider response SapiSendDocumentResponse has an invalid field "receivedAt".',
                self::SCHEMA,
                'receivedAt'
            );
        }

        return new DocumentSubmissionResult($providerDocumentId, $status, $timestamp, $receivedAt);
    }
}
