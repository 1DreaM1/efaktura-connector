<?php

namespace DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Response;

use DreaM1\EfakturaConnector\Core\Documents\SentDocumentParties;
use DreaM1\EfakturaConnector\Core\Exceptions\InvalidProviderResponseException;
use DreaM1\EfakturaConnector\Domain\EFaktura\Participant;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Response\DocumentMetadataFields;
use DreaM1\EfakturaConnector\Support\Exceptions\InvalidInputException;
use DreaM1\EfakturaConnector\Support\NonBlankString;

/** Parses participant identifiers returned by SAPI and Peppol Gateway responses. @internal */
final class ParticipantResponseParser
{
    /**
     * Parses one participant identifier and reports malformed provider data as a response error.
     *
     * @param  string                           $identifier Peppol participant identifier returned by the provider.
     * @param  string                           $schema     Response schema name used to identify validation errors.
     * @param  string                           $field      Response field name to read or validate.
     * @return Participant
     * @throws InvalidProviderResponseException when the identifier is malformed
     */
    public static function parse(string $identifier, string $schema, string $field): Participant
    {
        try {
            return Participant::parse($identifier);
        } catch (InvalidInputException $exception) {
            throw new InvalidProviderResponseException(
                'API response contains an invalid Peppol participant identifier.',
                $schema,
                $field,
                $exception
            );
        }
    }

    /**
     * Parses an optional participant identifier; an empty value is treated as absent.
     *
     * @param  string|null                      $identifier Optional Peppol participant identifier returned by the provider.
     * @param  string                           $schema     Response schema name used to identify validation errors.
     * @param  string                           $field      Response field name to read or validate.
     * @return Participant|null
     * @throws InvalidProviderResponseException when a non-empty identifier is malformed
     */
    public static function parseOptional(?string $identifier, string $schema, string $field): ?Participant
    {
        if ($identifier === null || ! NonBlankString::isNotBlank($identifier)) {
            return null;
        }

        return self::parse($identifier, $schema, $field);
    }

    /**
     * Parses the sender and receiver participant identifiers of a sent document or message-level status.
     *
     * A missing or blank identifier stays absent; a malformed non-blank one becomes a response error that
     * names the sender or receiver field.
     *
     * @param  string|null                      $senderId   Sender participant identifier returned by the provider.
     * @param  string|null                      $receiverId Receiver participant identifier returned by the provider.
     * @param  string                           $schema     Response schema name used to identify validation errors.
     * @return SentDocumentParties
     * @throws InvalidProviderResponseException when a non-empty identifier is malformed
     */
    public static function parseParties(?string $senderId, ?string $receiverId, string $schema): SentDocumentParties
    {
        return new SentDocumentParties(
            self::parseOptional($senderId, $schema, DocumentMetadataFields::SENDER_PARTICIPANT_ID),
            self::parseOptional($receiverId, $schema, DocumentMetadataFields::RECEIVER_PARTICIPANT_ID)
        );
    }
}
