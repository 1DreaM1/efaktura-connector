<?php

namespace DreaM1\EfakturaConnector\Core\Documents;

use DreaM1\EfakturaConnector\Domain\EFaktura\ProviderDocumentId;
use DreaM1\EfakturaConnector\Domain\EFaktura\ProviderDocumentStatus;
use DreaM1\EfakturaConnector\Support\Timestamp;

/** Provider-confirmed result of acknowledging a received document. */
final class ReceivedDocumentAcknowledgement
{
    private ProviderDocumentId $documentId;
    private ProviderDocumentStatus $status;
    private Timestamp $acknowledgedDateTime;

    /**
     * Creates the provider-confirmed result of acknowledging a received document.
     *
     * @param ProviderDocumentId     $documentId           Provider identifier of the acknowledged document.
     * @param ProviderDocumentStatus $status               Provider status returned after acknowledgement.
     * @param Timestamp              $acknowledgedDateTime Provider acknowledgement timestamp.
     */
    public function __construct(
        ProviderDocumentId $documentId,
        ProviderDocumentStatus $status,
        Timestamp $acknowledgedDateTime
    ) {
        $this->documentId           = $documentId;
        $this->status               = $status;
        $this->acknowledgedDateTime = $acknowledgedDateTime;
    }

    /** Returns the document ID acknowledged by the provider. */
    public function documentId(): ProviderDocumentId
    {
        return $this->documentId;
    }

    /** Returns the provider status after acknowledgement. */
    public function status(): ProviderDocumentStatus
    {
        return $this->status;
    }

    /** Returns the provider's acknowledgement timestamp. */
    public function acknowledgedDateTime(): Timestamp
    {
        return $this->acknowledgedDateTime;
    }
}
