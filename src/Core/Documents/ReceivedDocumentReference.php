<?php

namespace DreaM1\EfakturaConnector\Core\Documents;

use DreaM1\EfakturaConnector\Domain\EFaktura\Participant;
use DreaM1\EfakturaConnector\Domain\EFaktura\ProviderDocumentId;

/** Identifies a received document for payload retrieval and acknowledgement. */
final class ReceivedDocumentReference
{
    private Participant $participant;
    private ProviderDocumentId $providerDocumentId;
    private DocumentRequestContext $context;

    /**
     * Creates a reference for participant-scoped received-document operations.
     *
     * @param Participant            $participant        Participant that owns the received document.
     * @param ProviderDocumentId     $providerDocumentId Provider-assigned document identifier.
     * @param DocumentRequestContext $context            Context used for diagnostics and request coordination.
     */
    public function __construct(
        Participant $participant,
        ProviderDocumentId $providerDocumentId,
        DocumentRequestContext $context
    ) {
        $this->participant        = $participant;
        $this->providerDocumentId = $providerDocumentId;
        $this->context            = $context;
    }

    /** Returns the participant whose received document is being referenced. */
    public function participant(): Participant
    {
        return $this->participant;
    }

    /** Returns the provider-assigned ID used to retrieve or acknowledge the document. */
    public function providerDocumentId(): ProviderDocumentId
    {
        return $this->providerDocumentId;
    }

    /** Returns the request context used for diagnostic logging. */
    public function context(): DocumentRequestContext
    {
        return $this->context;
    }
}
