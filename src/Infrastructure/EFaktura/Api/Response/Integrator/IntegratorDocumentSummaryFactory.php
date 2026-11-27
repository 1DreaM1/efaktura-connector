<?php

namespace DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Response\Integrator;

use DreaM1\EfakturaConnector\Core\Exceptions\InvalidProviderResponseException;
use DreaM1\EfakturaConnector\Core\IntegratorApi\IntegratorDocumentSummary;
use DreaM1\EfakturaConnector\Core\IntegratorApi\ParticipantDocumentSummary;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Response\ProviderResponseReader;

/** Maps an Integrator API document-count summary response to the Core summary model. */
final class IntegratorDocumentSummaryFactory
{
    private const SCHEMA             = 'IntegratorDocumentSummaryResponse';
    private const PARTICIPANT_SCHEMA = 'ParticipantDocumentSummaryResponse';

    private function __construct()
    {
    }

    /**
     * Maps a decoded provider response object to Core monthly document counts.
     *
     * @param  array<array-key, mixed>          $data Decoded provider response object.
     * @return IntegratorDocumentSummary        Typed monthly document counts.
     * @throws InvalidProviderResponseException when a field has an invalid type or value.
     */
    public static function fromArray(array $data): IntegratorDocumentSummary
    {
        $reader       = new ProviderResponseReader($data, self::SCHEMA);
        $participants = $reader->optionalObjectList('participants', self::PARTICIPANT_SCHEMA);
        $mapped       = [];

        foreach ($participants ?? [] as $participant) {
            $mapped[] = new ParticipantDocumentSummary(
                IntegratorFieldReader::participantId($participant, self::PARTICIPANT_SCHEMA, 'participant_id'),
                $participant->optionalInt('sent'),
                $participant->optionalInt('received')
            );
        }

        return new IntegratorDocumentSummary($reader->optionalString('month'), $participants === null ? null : $mapped);
    }
}
