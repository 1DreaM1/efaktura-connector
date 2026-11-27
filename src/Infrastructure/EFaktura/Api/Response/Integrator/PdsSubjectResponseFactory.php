<?php

namespace DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Response\Integrator;

use DreaM1\EfakturaConnector\Core\Exceptions\InvalidProviderResponseException;
use DreaM1\EfakturaConnector\Core\IntegratorApi\PdsSubjectResponse;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Response\ProviderResponseReader;

/** Maps the Integrator API acknowledgement of a PDS subject submission to the Core model. */
final class PdsSubjectResponseFactory
{
    private const SCHEMA = 'PdsSubjectResponse';

    private function __construct()
    {
    }

    /**
     * Maps a decoded provider response object to a Core PDS subject acknowledgement.
     *
     * @param  array<array-key, mixed>          $data Decoded provider response object.
     * @return PdsSubjectResponse               Typed acknowledgement; both fields are optional in OpenAPI.
     * @throws InvalidProviderResponseException when a field has an invalid type or value.
     */
    public static function fromArray(array $data): PdsSubjectResponse
    {
        $reader = new ProviderResponseReader($data, self::SCHEMA);

        return new PdsSubjectResponse(
            $reader->optionalInt('code'),
            $reader->optionalString('description')
        );
    }
}
