<?php

namespace DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Response\Integrator;

use DreaM1\EfakturaConnector\Core\Configuration\OAuthClientSecret;
use DreaM1\EfakturaConnector\Core\Exceptions\InvalidProviderResponseException;
use DreaM1\EfakturaConnector\Core\IntegratorApi\IntegratorClientCreation;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Response\ProviderResponseReader;

/** Maps an Integrator API OAuth client creation response, with its one-time secret, to the Core model. */
final class IntegratorClientCreationFactory
{
    private const SCHEMA = 'IntegratorClientCreateResponse';

    private function __construct()
    {
    }

    /**
     * Maps a decoded provider response object to Core client-creation metadata.
     *
     * @param  array<array-key, mixed>          $data Decoded provider response object.
     * @return IntegratorClientCreation         Typed metadata and one-time credentials, when returned.
     * @throws InvalidProviderResponseException when a field has an invalid type or value.
     */
    public static function fromArray(array $data): IntegratorClientCreation
    {
        $reader = new ProviderResponseReader($data, self::SCHEMA);
        $secret = $reader->optionalNonBlankString('client_secret');

        return new IntegratorClientCreation(
            IntegratorFieldReader::clientReference($reader),
            $secret !== null ? OAuthClientSecret::fromString($secret) : null,
            $reader->optionalString('client_name'),
            IntegratorFieldReader::clientStatus($reader),
            IntegratorFieldReader::accessList(
                $reader->optionalObjectList('participants', 'IntegratorClientParticipantAccess')
            )
        );
    }
}
