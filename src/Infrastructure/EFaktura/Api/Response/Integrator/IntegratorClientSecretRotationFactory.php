<?php

namespace DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Response\Integrator;

use DreaM1\EfakturaConnector\Core\Configuration\OAuthClientSecret;
use DreaM1\EfakturaConnector\Core\Exceptions\InvalidProviderResponseException;
use DreaM1\EfakturaConnector\Core\IntegratorApi\IntegratorClientSecretRotation;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Response\ProviderResponseReader;

/** Maps an Integrator API rotated-secret response to the Core rotation model. */
final class IntegratorClientSecretRotationFactory
{
    private const SCHEMA = 'IntegratorClientSecretResponse';

    private function __construct()
    {
    }

    /**
     * Maps a decoded provider response object to a Core client secret rotation.
     *
     * @param  array<array-key, mixed>          $data Decoded provider response object.
     * @return IntegratorClientSecretRotation   New one-time secret, when returned.
     * @throws InvalidProviderResponseException when a field has an invalid type or value.
     */
    public static function fromArray(array $data): IntegratorClientSecretRotation
    {
        $reader = new ProviderResponseReader($data, self::SCHEMA);
        $secret = $reader->optionalNonBlankString('client_secret');

        return new IntegratorClientSecretRotation(
            IntegratorFieldReader::clientReference($reader),
            $secret !== null ? OAuthClientSecret::fromString($secret) : null
        );
    }
}
