<?php

namespace DreaM1\EfakturaConnector\Core\Connection;

use DreaM1\EfakturaConnector\Domain\EFaktura\Participant;
use DreaM1\EfakturaConnector\Domain\EFaktura\PeppolDocumentTypeIdentifier;
use DreaM1\EfakturaConnector\Domain\EFaktura\PeppolProcessIdentifier;
use DreaM1\EfakturaConnector\Support\NonBlankString;

/** Request data for checking whether a participant receives a document type and process. */
final class CheckReceivingCapabilityRequest
{
    private Participant $participant;
    private PeppolDocumentTypeIdentifier $documentTypeId;
    private ?PeppolProcessIdentifier $processId;

    /**
     * Creates a discovery request for a participant and Peppol document type.
     *
     * @param Participant                  $participant    Participant whose identity scopes the provider operation.
     * @param PeppolDocumentTypeIdentifier $documentTypeId Peppol document type identifier.
     * @param PeppolProcessIdentifier|null $processId      Peppol process identifier.
     */
    public function __construct(
        Participant $participant,
        PeppolDocumentTypeIdentifier $documentTypeId,
        ?PeppolProcessIdentifier $processId = null
    ) {
        NonBlankString::assertNotBlank(
            $participant->identifier(),
            'Discovery requires participant and document type identifiers.'
        );

        $this->participant    = $participant;
        $this->documentTypeId = $documentTypeId;
        $this->processId      = $processId;
    }

    /** Returns the participant being queried. */
    public function participant(): Participant
    {
        return $this->participant;
    }

    /**
     * Returns the Peppol document type identifier being checked.
     *
     * @return PeppolDocumentTypeIdentifier
     */
    public function documentTypeId(): PeppolDocumentTypeIdentifier
    {
        return $this->documentTypeId;
    }

    /**
     * Returns the optional Peppol process identifier being checked.
     *
     * @return PeppolProcessIdentifier|null
     */
    public function processId(): ?PeppolProcessIdentifier
    {
        return $this->processId;
    }
}
