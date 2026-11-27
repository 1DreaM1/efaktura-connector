<?php

namespace DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Response\PeppolGateway;

use DreaM1\EfakturaConnector\Core\Connection\Health\ParticipantCapability;
use DreaM1\EfakturaConnector\Domain\EFaktura\PeppolDocumentTypeIdentifier;
use DreaM1\EfakturaConnector\Domain\EFaktura\PeppolProcessIdentifier;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Response\DocumentMetadataFields;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Response\ProviderResponseReader;

/**
 * Maps the Peppol Gateway ParticipantCapability schema to the public capability model:
 * { documentTypeId, processId, transportProfile? }
 *
 * Shared by the participant profile and the receiving-capability check.
 */
final class ParticipantCapabilityFactory
{
    /** Provider schema name used for nested capability objects in error messages. */
    public const SCHEMA = 'ParticipantCapability';

    private function __construct()
    {
    }

    /**
     * Maps validated provider fields to a capability.
     *
     * @param  ProviderResponseReader $response Validated access to the capability object.
     * @return ParticipantCapability
     */
    public static function fromData(ProviderResponseReader $response): ParticipantCapability
    {
        return new ParticipantCapability(
            PeppolDocumentTypeIdentifier::fromString(
                $response->requiredString(DocumentMetadataFields::DOCUMENT_TYPE_ID)
            ),
            PeppolProcessIdentifier::fromString($response->requiredString(DocumentMetadataFields::PROCESS_ID)),
            $response->nullableString('transportProfile')
        );
    }
}
