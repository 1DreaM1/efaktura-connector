<?php

namespace DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Response\Integrator;

use DreaM1\EfakturaConnector\Core\Exceptions\InvalidProviderResponseException;
use DreaM1\EfakturaConnector\Core\IntegratorApi\IntegratorParticipantList;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Response\ProviderResponseReader;

/** Maps an Integrator API participant list page to the Core list model. */
final class IntegratorParticipantListFactory
{
    private const SCHEMA = 'IntegratorParticipantListResponse';

    private function __construct()
    {
    }

    /**
     * Maps a decoded provider response object to a Core participant list page.
     *
     * @param  array<array-key, mixed>          $data Decoded provider response object.
     * @return IntegratorParticipantList        Typed participant values and the optional continuation token.
     * @throws InvalidProviderResponseException when a field has an invalid type or value.
     */
    public static function fromArray(array $data): IntegratorParticipantList
    {
        $reader       = new ProviderResponseReader($data, self::SCHEMA);
        $participants = $reader->optionalObjectList('participants', 'IntegratorParticipantResponse');
        $mapped       = [];

        foreach ($participants ?? [] as $participant) {
            $mapped[] = IntegratorParticipantFactory::fromArray($participant->toArray());
        }

        return new IntegratorParticipantList($mapped, $reader->optionalPageToken('nextPageToken'));
    }
}
