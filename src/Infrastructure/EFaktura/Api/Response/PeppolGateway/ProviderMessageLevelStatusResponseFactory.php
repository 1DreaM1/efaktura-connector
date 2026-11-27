<?php

namespace DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Response\PeppolGateway;

use DreaM1\EfakturaConnector\Core\Exceptions\InvalidProviderResponseException;
use DreaM1\EfakturaConnector\Core\MessageStatuses\ProviderMessageLevelStatus;
use DreaM1\EfakturaConnector\Core\MessageStatuses\ProviderMessageLevelStatusReason;
use DreaM1\EfakturaConnector\Core\MessageStatuses\ProviderMessageLevelStatusResponse;
use DreaM1\EfakturaConnector\Core\MessageStatuses\ProviderMessageLevelStatusResult;
use DreaM1\EfakturaConnector\Domain\EFaktura\MessageLevelStatusCode;
use DreaM1\EfakturaConnector\Domain\EFaktura\MessageLevelStatusIdentifier;
use DreaM1\EfakturaConnector\Domain\EFaktura\PeppolDocumentTypeIdentifier;
use DreaM1\EfakturaConnector\Domain\EFaktura\ProviderMessageId;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Response\DocumentMetadataFields;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Response\ParticipantResponseParser;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Response\ProviderResponseReader;
use DreaM1\EfakturaConnector\Support\JsonArrayDecoder;

/**
 * Maps GET /v1/sent-documents/{identifier}/message-level-statuses → 200 MessageLevelStatusListResponse to the
 * public message-level status response.
 *
 * Items are parsed independently, so one malformed status does not discard its valid siblings; the number of
 * skipped records is reported on the result.
 */
final class ProviderMessageLevelStatusResponseFactory
{
    private const SCHEMA = 'MessageLevelStatusListResponse';

    private const ITEM_SCHEMA = 'MessageLevelStatusResponse';

    private function __construct()
    {
    }

    /**
     * Maps a decoded provider response object to the public message-level status response.
     *
     * @param  array<array-key, mixed>            $data Decoded provider response object.
     * @return ProviderMessageLevelStatusResponse
     * @throws InvalidProviderResponseException   when the items field is not a list.
     */
    public static function fromArray(array $data): ProviderMessageLevelStatusResponse
    {
        return self::fromData(new ProviderResponseReader($data, self::SCHEMA));
    }

    /**
     * Maps validated provider fields to the public message-level status response.
     *
     * @param  ProviderResponseReader             $response Validated access to the provider fields.
     * @return ProviderMessageLevelStatusResponse
     * @throws InvalidProviderResponseException   when the items field is not a list.
     */
    public static function fromData(ProviderResponseReader $response): ProviderMessageLevelStatusResponse
    {
        $items = $response->optionalList('items');

        if ($items === null) {
            // An omitted items array is treated as no statuses.
            return ProviderMessageLevelStatusResponse::create(true, [], 0);
        }

        $statuses     = [];
        $invalidItems = 0;

        foreach ($items as $item) {
            try {
                $itemData = JsonArrayDecoder::objectAsArray($item);

                if ($itemData === null) {
                    throw new InvalidProviderResponseException('Message level status item is not an object.');
                }

                $statuses[] = self::status(new ProviderResponseReader($itemData, self::ITEM_SCHEMA));
            } catch (InvalidProviderResponseException $exception) {
                // Keep the list usable and report the number of skipped provider records.
                $invalidItems++;
            }
        }

        return ProviderMessageLevelStatusResponse::create(true, $statuses, $invalidItems);
    }

    private static function status(ProviderResponseReader $data): ProviderMessageLevelStatus
    {
        $identifierValue       = $data->optionalNonBlankString('identifier');
        $messageIdValue        = $data->optionalNonBlankString('messageId');
        $responseCodeValue     = $data->optionalNonBlankString('responseCode');
        $documentTypeIdValue   = $data->optionalNonBlankString(DocumentMetadataFields::DOCUMENT_TYPE_ID);
        $senderParticipantId   = $data->optionalStringAllowBlank(DocumentMetadataFields::SENDER_PARTICIPANT_ID);
        $receiverParticipantId = $data->optionalStringAllowBlank(DocumentMetadataFields::RECEIVER_PARTICIPANT_ID);

        // An absent reasons array means no reasons.
        $reasons = [];

        foreach ($data->optionalObjectList('reasons', 'MessageLevelStatusReason') ?? [] as $reason) {
            $reasons[] = ProviderMessageLevelStatusReason::create(
                $reason->optionalString('lineId'),
                $reason->optionalString('code'),
                $reason->optionalString('description')
            );
        }

        $description = $data->optionalString('responseDescription');
        $receivedAt  = $data->optionalTimestamp('receivedAt');

        return ProviderMessageLevelStatus::create(
            $identifierValue !== null ? MessageLevelStatusIdentifier::fromString($identifierValue) : null,
            $messageIdValue !== null ? ProviderMessageId::fromString($messageIdValue) : null,
            new ProviderMessageLevelStatusResult(
                $responseCodeValue !== null ? MessageLevelStatusCode::fromString($responseCodeValue) : null,
                $description,
                $reasons
            ),
            // Absent participant fields stay null; malformed identifiers that were supplied become response errors.
            ParticipantResponseParser::parseParties($senderParticipantId, $receiverParticipantId, self::ITEM_SCHEMA),
            $documentTypeIdValue !== null ? PeppolDocumentTypeIdentifier::fromString($documentTypeIdValue) : null,
            $receivedAt
        );
    }
}
