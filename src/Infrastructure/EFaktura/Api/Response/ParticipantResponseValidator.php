<?php

namespace DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Response;

use DreaM1\EfakturaConnector\Core\Exceptions\InvalidProviderResponseException;
use DreaM1\EfakturaConnector\Domain\EFaktura\Participant;

/** Validates that a provider response belongs to the participant requested by the caller. */
final class ParticipantResponseValidator
{
    /**
     * Rejects responses whose participant identity differs from the requested identity.
     *
     * @param  Participant                      $expected     Participant requested by the caller.
     * @param  Participant                      $actual       Participant identified in the provider response.
     * @param  string                           $errorMessage Diagnostic message for a mismatch exception.
     * @throws InvalidProviderResponseException when the identities differ
     */
    public static function assertMatches(
        Participant $expected,
        Participant $actual,
        string $errorMessage
    ): void {
        if (! $actual->equals($expected)) {
            throw new InvalidProviderResponseException($errorMessage);
        }
    }
}
