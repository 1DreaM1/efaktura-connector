<?php

namespace DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Response\PeppolGateway;

use DreaM1\EfakturaConnector\Core\Documents\SendDocumentsResult;
use DreaM1\EfakturaConnector\Core\Exceptions\InvalidProviderResponseException;
use DreaM1\EfakturaConnector\Domain\EFaktura\ProviderDocumentId;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Response\ProviderResponseReader;

/**
 * Maps POST /v1/send-documents → 200 SendDocumentsResponse: { count, identifiers } to the public batch result.
 *
 * The response schema and SendDocumentsResult carry the same two values and the same consistency rule, so no
 * separate response object is kept.
 */
final class SendDocumentsResultFactory
{
    private const SCHEMA = 'SendDocumentsResponse';

    private function __construct()
    {
    }

    /**
     * Maps a decoded provider response object to the public batch result.
     *
     * @param  array<array-key, mixed>          $data Decoded provider response object.
     * @return SendDocumentsResult
     * @throws InvalidProviderResponseException when a required field is invalid or the fields are inconsistent
     */
    public static function fromArray(array $data): SendDocumentsResult
    {
        return self::fromData(new ProviderResponseReader($data, self::SCHEMA));
    }

    /**
     * Maps validated provider fields to the public batch result.
     *
     * @param  ProviderResponseReader           $response Validated access to the provider fields.
     * @return SendDocumentsResult
     * @throws InvalidProviderResponseException when a required field is invalid or the fields are inconsistent
     */
    public static function fromData(ProviderResponseReader $response): SendDocumentsResult
    {
        $ids = array_map(
            static fn (string $identifier): ProviderDocumentId => ProviderDocumentId::fromString($identifier),
            $response->requiredStringList('identifiers')
        );
        $count = $response->requiredInt('count');

        if ($count < 0) {
            throw new InvalidProviderResponseException(
                'Provider response SendDocumentsResponse has an invalid field "count".',
                self::SCHEMA,
                'count'
            );
        }

        if ($count !== count($ids)) {
            throw new InvalidProviderResponseException(
                'Provider response SendDocumentsResponse has inconsistent fields "count" and "identifiers".',
                self::SCHEMA,
                'count'
            );
        }

        return new SendDocumentsResult($count, $ids);
    }
}
