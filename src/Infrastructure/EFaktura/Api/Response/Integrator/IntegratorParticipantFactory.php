<?php

namespace DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Response\Integrator;

use DreaM1\EfakturaConnector\Core\Exceptions\InvalidProviderResponseException;
use DreaM1\EfakturaConnector\Core\IntegratorApi\IntegratorParticipant;
use DreaM1\EfakturaConnector\Core\IntegratorApi\IntegratorParticipantCompany;
use DreaM1\EfakturaConnector\Core\IntegratorApi\IntegratorParticipantContract;
use DreaM1\EfakturaConnector\Core\IntegratorApi\IntegratorParticipantTimestamps;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Response\ProviderResponseReader;

/** Maps an Integrator API participant response to the Core participant model. */
final class IntegratorParticipantFactory
{
    private const SCHEMA = 'IntegratorParticipantResponse';

    private function __construct()
    {
    }

    /**
     * Maps a decoded provider response object to Core participant metadata.
     *
     * @param  array<array-key, mixed>          $data Decoded provider response object.
     * @return IntegratorParticipant            Typed participant metadata.
     * @throws InvalidProviderResponseException when a field has an invalid type or value.
     */
    public static function fromArray(array $data): IntegratorParticipant
    {
        $reader = new ProviderResponseReader($data, self::SCHEMA);

        return new IntegratorParticipant(
            IntegratorFieldReader::participantId($reader, self::SCHEMA, 'participant_id'),
            new IntegratorParticipantCompany(
                $reader->optionalString('dic'),
                $reader->optionalString('legal_name'),
                $reader->optionalString('company_email'),
                $reader->optionalString('company_phone')
            ),
            new IntegratorParticipantTimestamps(
                $reader->optionalTimestamp('source_created_at'),
                $reader->optionalTimestamp('received_at')
            ),
            new IntegratorParticipantContract(
                $reader->optionalBool('internal_transfer_required'),
                $reader->optionalBool('is_owner'),
                IntegratorFieldReader::contractMode($reader, 'active_contract_mode')
            )
        );
    }
}
