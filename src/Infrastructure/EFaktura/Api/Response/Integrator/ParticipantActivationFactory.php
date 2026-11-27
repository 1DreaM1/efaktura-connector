<?php

namespace DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Response\Integrator;

use DreaM1\EfakturaConnector\Core\Exceptions\InvalidProviderResponseException;
use DreaM1\EfakturaConnector\Core\IntegratorApi\ParticipantActivation;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Response\ProviderResponseReader;

/** Maps an Integrator API participant activation response to the Core activation model. */
final class ParticipantActivationFactory
{
    private const SCHEMA = 'ParticipantActivationResponse';

    private function __construct()
    {
    }

    /**
     * Maps a decoded provider response object to Core participant activation metadata.
     *
     * @param  array<array-key, mixed>          $data Decoded provider response object.
     * @return ParticipantActivation            Typed activation metadata.
     * @throws InvalidProviderResponseException when a field has an invalid type or value.
     */
    public static function fromArray(array $data): ParticipantActivation
    {
        $reader = new ProviderResponseReader($data, self::SCHEMA);

        return new ParticipantActivation(
            $reader->optionalNonBlankString('activation_id'),
            IntegratorFieldReader::participantId($reader, self::SCHEMA, 'participant_id'),
            IntegratorFieldReader::integratorId($reader),
            IntegratorFieldReader::contractMode($reader, 'mode'),
            $reader->optionalString('status'),
            $reader->optionalTimestamp('requested_at')
        );
    }
}
