<?php

namespace DreaM1\EfakturaConnector\Core\Documents;

use DreaM1\EfakturaConnector\Domain\EFaktura\ProviderDocumentId;
use DreaM1\EfakturaConnector\Domain\EFaktura\ProviderDocumentStatus;
use DreaM1\EfakturaConnector\Support\Timestamp;

/**
 * Outcome of POST /sapi/document/send (schema SapiSendDocumentResponse).
 *
 * Times retain the ISO 8601 text and parsed instant returned by the provider:
 *   timestamp  — when the provider decided about the submission (ACCEPTED / REJECTED);
 *                orders it among later status updates
 *   receivedAt — when the provider took over the document; absent e.g. for a rejected submission
 */
final class DocumentSubmissionResult
{
    private ProviderDocumentId $providerDocumentId;
    private ProviderDocumentStatus $status;
    private Timestamp $timestamp;
    private ?Timestamp $receivedAt;

    /**
     * @param ProviderDocumentId     $providerDocumentId Document identifier assigned by the provider.
     * @param ProviderDocumentStatus $status             Provider-reported document status.
     * @param Timestamp              $timestamp          Submission decision timestamp.
     * @param Timestamp|null         $receivedAt         Timestamp when the provider received the document.
     */
    public function __construct(
        ProviderDocumentId $providerDocumentId,
        ProviderDocumentStatus $status,
        Timestamp $timestamp,
        ?Timestamp $receivedAt = null
    ) {
        $this->providerDocumentId = $providerDocumentId;
        $this->status             = $status;
        $this->timestamp          = $timestamp;
        $this->receivedAt         = $receivedAt;
    }

    /**
     * Returns the provider document ID.
     *
     * @return ProviderDocumentId
     */
    public function providerDocumentId(): ProviderDocumentId
    {
        return $this->providerDocumentId;
    }

    /**
     * Returns the status.
     *
     * @return ProviderDocumentStatus
     */
    public function status(): ProviderDocumentStatus
    {
        return $this->status;
    }

    /**
     * Returns the submission decision timestamp.
     */
    public function timestamp(): Timestamp
    {
        return $this->timestamp;
    }

    /**
     * Returns the provider's receipt timestamp, or null when the document is not yet received.
     */
    public function receivedAt(): ?Timestamp
    {
        return $this->receivedAt;
    }
}
