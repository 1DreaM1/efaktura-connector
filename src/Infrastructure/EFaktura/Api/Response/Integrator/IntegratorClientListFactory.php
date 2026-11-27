<?php

namespace DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Response\Integrator;

use DreaM1\EfakturaConnector\Core\Exceptions\InvalidProviderResponseException;
use DreaM1\EfakturaConnector\Core\IntegratorApi\IntegratorClientList;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Response\ProviderResponseReader;

/** Maps an Integrator API OAuth client list page to the Core list model. */
final class IntegratorClientListFactory
{
    private const SCHEMA = 'IntegratorClientListResponse';

    private function __construct()
    {
    }

    /**
     * Maps a decoded provider response object to a Core client list page.
     *
     * @param  array<array-key, mixed>          $data Decoded provider response object.
     * @return IntegratorClientList             Typed client values and the optional continuation token.
     * @throws InvalidProviderResponseException when a field has an invalid type or value.
     */
    public static function fromArray(array $data): IntegratorClientList
    {
        $reader  = new ProviderResponseReader($data, self::SCHEMA);
        $clients = $reader->optionalObjectList('clients', 'IntegratorClientResponse');
        $mapped  = [];

        foreach ($clients ?? [] as $client) {
            $mapped[] = IntegratorClientFactory::fromArray($client->toArray());
        }

        return new IntegratorClientList($mapped, $reader->optionalPageToken('nextPageToken'));
    }
}
