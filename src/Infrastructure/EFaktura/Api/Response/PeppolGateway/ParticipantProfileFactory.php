<?php

namespace DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Response\PeppolGateway;

use DreaM1\EfakturaConnector\Core\Connection\Health\ParticipantCapabilities;
use DreaM1\EfakturaConnector\Core\Connection\Health\ParticipantProfile;
use DreaM1\EfakturaConnector\Core\Exceptions\InvalidProviderResponseException;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Response\ParticipantResponseParser;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Response\ProviderResponseReader;

/**
 * Maps GET /v1/participants/{participantId} → 200 ParticipantResponse to the public participant profile:
 * { participantId, reachable, name?, capabilities: { documentTypeId, processId, transportProfile? }[] }
 *
 * The response schema and ParticipantProfile carry the same values, so no separate response object is kept.
 */
final class ParticipantProfileFactory
{
    private const SCHEMA = 'ParticipantResponse';

    private function __construct()
    {
    }

    /**
     * Maps a decoded provider response object to the public participant profile.
     *
     * @param  array<array-key, mixed>          $data Decoded provider response object.
     * @return ParticipantProfile
     * @throws InvalidProviderResponseException when a required field is missing or invalid.
     */
    public static function fromArray(array $data): ParticipantProfile
    {
        return self::fromData(new ProviderResponseReader($data, self::SCHEMA));
    }

    /**
     * Maps validated provider fields to the public participant profile.
     *
     * @param  ProviderResponseReader           $response Validated access to the provider fields.
     * @return ParticipantProfile
     * @throws InvalidProviderResponseException when a required field is missing or invalid.
     */
    public static function fromData(ProviderResponseReader $response): ParticipantProfile
    {
        $capabilities = array_map(
            [ParticipantCapabilityFactory::class, 'fromData'],
            $response->requiredObjectList('capabilities', ParticipantCapabilityFactory::SCHEMA)
        );

        return new ParticipantProfile(
            ParticipantResponseParser::parse(
                $response->requiredString('participantId'),
                self::SCHEMA,
                'participantId'
            ),
            $response->requiredBool('reachable'),
            $response->nullableString('name'),
            new ParticipantCapabilities($capabilities)
        );
    }
}
