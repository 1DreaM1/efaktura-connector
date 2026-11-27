<?php

namespace DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Response\PeppolGateway;

use DreaM1\EfakturaConnector\Core\Connection\CheckReceivingCapabilityResponse;
use DreaM1\EfakturaConnector\Core\Exceptions\InvalidProviderResponseException;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Response\ParticipantResponseParser;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Response\ProviderResponseReader;

/**
 * Maps POST /v1/discovery/receives → 200 DiscoveryReceivesResponse to the public capability-check result:
 * { participantId, receives, endpoint?, capability? }
 *
 * The response schema and CheckReceivingCapabilityResponse carry the same values, so no separate response object
 * is kept.
 */
final class CheckReceivingCapabilityResponseFactory
{
    private const SCHEMA = 'DiscoveryReceivesResponse';

    private function __construct()
    {
    }

    /**
     * Maps a decoded provider response object to the public capability-check result.
     *
     * @param  array<array-key, mixed>          $data Decoded provider response object.
     * @return CheckReceivingCapabilityResponse
     * @throws InvalidProviderResponseException when a required field is missing or invalid.
     */
    public static function fromArray(array $data): CheckReceivingCapabilityResponse
    {
        return self::fromData(new ProviderResponseReader($data, self::SCHEMA));
    }

    /**
     * Maps validated provider fields to the public capability-check result.
     *
     * @param  ProviderResponseReader           $response Validated access to the provider fields.
     * @return CheckReceivingCapabilityResponse
     * @throws InvalidProviderResponseException when a required field is missing or invalid.
     */
    public static function fromData(ProviderResponseReader $response): CheckReceivingCapabilityResponse
    {
        $capabilityData = $response->nullableObject('capability', ParticipantCapabilityFactory::SCHEMA);

        return new CheckReceivingCapabilityResponse(
            ParticipantResponseParser::parse(
                $response->requiredString('participantId'),
                self::SCHEMA,
                'participantId'
            ),
            $response->requiredBool('receives'),
            $response->nullableString('endpoint'),
            $capabilityData === null ? null : ParticipantCapabilityFactory::fromData($capabilityData)
        );
    }
}
