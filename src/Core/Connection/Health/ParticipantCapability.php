<?php

namespace DreaM1\EfakturaConnector\Core\Connection\Health;

use DreaM1\EfakturaConnector\Domain\EFaktura\PeppolDocumentTypeIdentifier;
use DreaM1\EfakturaConnector\Domain\EFaktura\PeppolProcessIdentifier;

/** A single participant capability pairing a document type with a process identifier. */
final class ParticipantCapability
{
    private PeppolDocumentTypeIdentifier $documentTypeId;
    private PeppolProcessIdentifier $processId;
    private ?string $transportProfile;

    /**
     * Creates an advertised capability for a Peppol document type and business process.
     *
     * @param PeppolDocumentTypeIdentifier $documentTypeId   Peppol identifier of the document type the participant can receive.
     * @param PeppolProcessIdentifier      $processId        Peppol identifier of the business process supported for that type.
     * @param string|null                  $transportProfile Identifier of the supported transport profile, when provided.
     */
    public function __construct(
        PeppolDocumentTypeIdentifier $documentTypeId,
        PeppolProcessIdentifier $processId,
        ?string $transportProfile
    ) {
        $this->documentTypeId   = $documentTypeId;
        $this->processId        = $processId;
        $this->transportProfile = $transportProfile;
    }

    /** Returns the Peppol document type identifier. */
    public function documentTypeId(): PeppolDocumentTypeIdentifier
    {
        return $this->documentTypeId;
    }

    /** Returns the Peppol process identifier. */
    public function processId(): PeppolProcessIdentifier
    {
        return $this->processId;
    }

    /** Returns the transport profile advertised for this document type and process, when provided. */
    public function transportProfile(): ?string
    {
        return $this->transportProfile;
    }
}
