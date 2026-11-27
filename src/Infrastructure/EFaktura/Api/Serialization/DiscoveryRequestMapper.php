<?php

namespace DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Serialization;

use DreaM1\EfakturaConnector\Core\Connection\CheckReceivingCapabilityRequest;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Response\DocumentMetadataFields;

/**
 * Maps a typed receiving-capability check to the Peppol Gateway DiscoveryReceivesRequest payload.
 *
 * @internal
 */
final class DiscoveryRequestMapper
{
    private function __construct()
    {
    }

    /**
     * Builds the request body; an absent process ID is omitted.
     *
     * @param  CheckReceivingCapabilityRequest $request Participant and document type, with an optional process.
     * @return array<string, string>
     */
    public static function receivesBody(CheckReceivingCapabilityRequest $request): array
    {
        $body = [
            'participantId'                          => $request->participant()->identifier(),
            DocumentMetadataFields::DOCUMENT_TYPE_ID => $request->documentTypeId()->value(),
        ];

        if ($request->processId() !== null) {
            $body[DocumentMetadataFields::PROCESS_ID] = $request->processId()->value();
        }

        return $body;
    }
}
