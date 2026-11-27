<?php

namespace DreaM1\EfakturaConnector\Core\Documents;

use DreaM1\EfakturaConnector\Domain\EFaktura\Participant;
use DreaM1\EfakturaConnector\Domain\EFaktura\ProviderDocumentId;

/** Identifies a sent document for tracking and message-level status operations. */
final class SentDocumentReference
{
    private Participant $participant;
    private ProviderDocumentId $identifier;
    private DocumentRequestContext $context;

    /**
     * Creates a reference for participant-scoped sent-document operations.
     *
     * @param Participant            $participant Participant that submitted the document.
     * @param ProviderDocumentId     $identifier  Provider-assigned document identifier.
     * @param DocumentRequestContext $context     Context used for diagnostics and request coordination.
     */
    public function __construct(
        Participant $participant,
        ProviderDocumentId $identifier,
        DocumentRequestContext $context
    ) {
        $this->participant = $participant;
        $this->identifier  = $identifier;
        $this->context     = $context;
    }

    /** Returns the participant whose sent document is being referenced. */
    public function participant(): Participant
    {
        return $this->participant;
    }

    /** Returns the provider identifier used to track the sent document. */
    public function identifier(): ProviderDocumentId
    {
        return $this->identifier;
    }

    /** Returns the request context used for diagnostic logging. */
    public function context(): DocumentRequestContext
    {
        return $this->context;
    }
}
