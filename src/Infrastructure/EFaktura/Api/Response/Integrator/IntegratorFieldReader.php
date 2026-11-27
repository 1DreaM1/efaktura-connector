<?php

namespace DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Response\Integrator;

use DreaM1\EfakturaConnector\Core\Configuration\OAuthClientId;
use DreaM1\EfakturaConnector\Core\IntegratorApi\IntegratorClientStatus;
use DreaM1\EfakturaConnector\Core\IntegratorApi\IntegratorId;
use DreaM1\EfakturaConnector\Core\IntegratorApi\IntegratorParticipantAccessResponse;
use DreaM1\EfakturaConnector\Core\IntegratorApi\ParticipantContractMode;
use DreaM1\EfakturaConnector\Domain\EFaktura\Participant;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Response\ParticipantResponseParser;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Response\ProviderResponseReader;

/** Reads optional Integrator API fields into the typed values shared by several response factories. @internal */
final class IntegratorFieldReader
{
    private function __construct()
    {
    }

    /**
     * Maps the optional participant access entries of an OAuth client.
     *
     * @param  ProviderResponseReader[]|null              $readers Validated access to each access entry.
     * @return IntegratorParticipantAccessResponse[]|null Typed entries, or null when the list is absent.
     */
    public static function accessList(?array $readers): ?array
    {
        if ($readers === null) {
            return null;
        }

        $access = [];

        foreach ($readers as $reader) {
            $access[] = new IntegratorParticipantAccessResponse(
                self::participantId($reader, 'IntegratorClientParticipantAccess', 'participant_id'),
                $reader->optionalBool('can_send'),
                $reader->optionalBool('can_receive')
            );
        }

        return $access;
    }

    /** Maps an optional provider client status to its value object. */
    public static function clientStatus(ProviderResponseReader $reader): ?IntegratorClientStatus
    {
        $status = $reader->optionalString('status');

        return $status !== null ? IntegratorClientStatus::fromProvider($status) : null;
    }

    /** Maps an optional provider contract mode to its value object. */
    public static function contractMode(ProviderResponseReader $reader, string $field): ?ParticipantContractMode
    {
        $mode = $reader->optionalString($field);

        return $mode !== null ? ParticipantContractMode::fromProvider($mode) : null;
    }

    /** Maps an optional OAuth client ID to its provider reference value object. */
    public static function clientReference(ProviderResponseReader $reader): ?OAuthClientId
    {
        $clientId = $reader->optionalNonBlankString('client_id');

        return $clientId !== null ? OAuthClientId::fromString($clientId) : null;
    }

    /** Maps an optional Peppol participant ID to the shared domain type. */
    public static function participantId(ProviderResponseReader $reader, string $schema, string $field): ?Participant
    {
        $participantId = $reader->optionalNonBlankString($field);

        return ParticipantResponseParser::parseOptional($participantId, $schema, $field);
    }

    /** Maps an optional integrator ID to its value object. */
    public static function integratorId(ProviderResponseReader $reader): ?IntegratorId
    {
        $integratorId = $reader->optionalNonBlankString('integrator_id');

        return $integratorId !== null ? IntegratorId::fromString($integratorId) : null;
    }
}
