<?php

namespace DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Response\Integrator;

use DreaM1\EfakturaConnector\Core\Exceptions\InvalidProviderResponseException;
use DreaM1\EfakturaConnector\Core\IntegratorApi\IntegratorClient;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Response\ProviderResponseReader;

/** Maps an Integrator API OAuth client response to the Core client model. */
final class IntegratorClientFactory
{
    private const SCHEMA = 'IntegratorClientResponse';

    private function __construct()
    {
    }

    /**
     * Maps a decoded provider response object to Core OAuth client metadata.
     *
     * @param  array<array-key, mixed>          $data Decoded provider response object.
     * @return IntegratorClient                 Typed OAuth client metadata.
     * @throws InvalidProviderResponseException when a field has an invalid type or value.
     */
    public static function fromArray(array $data): IntegratorClient
    {
        $reader = new ProviderResponseReader($data, self::SCHEMA);

        return new IntegratorClient(
            IntegratorFieldReader::clientReference($reader),
            $reader->optionalString('client_name'),
            IntegratorFieldReader::clientStatus($reader),
            $reader->optionalTimestamp('created_at'),
            $reader->optionalTimestamp('approved_at'),
            IntegratorFieldReader::accessList(
                $reader->optionalObjectList('participants', 'IntegratorClientParticipantAccess')
            )
        );
    }
}
