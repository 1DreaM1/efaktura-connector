<?php

namespace DreaM1\EfakturaConnector\Core\Documents;

use DreaM1\EfakturaConnector\Domain\EFaktura\ProviderDocumentId;
use DreaM1\EfakturaConnector\Domain\EFaktura\ProviderMessageId;

/** Downstream provider identifiers reported for a sent document. */
final class SentDocumentProviderReference
{
    private ?string $name;
    private ?ProviderDocumentId $documentId;
    private ?ProviderMessageId $messageId;

    /**
     * Creates the downstream provider identifiers reported for a sent document.
     *
     * @param string|null             $name       Downstream provider name, when supplied.
     * @param ProviderDocumentId|null $documentId Downstream document identifier, when supplied.
     * @param ProviderMessageId|null  $messageId  Downstream message identifier, when supplied.
     */
    public function __construct(?string $name, ?ProviderDocumentId $documentId, ?ProviderMessageId $messageId)
    {
        $this->name       = $name;
        $this->documentId = $documentId;
        $this->messageId  = $messageId;
    }

    /** Returns the downstream provider name, or null when unavailable. */
    public function name(): ?string
    {
        return $this->name;
    }

    /** Returns the downstream document identifier, or null when unavailable. */
    public function documentId(): ?ProviderDocumentId
    {
        return $this->documentId;
    }

    /** Returns the downstream message identifier, or null when unavailable. */
    public function messageId(): ?ProviderMessageId
    {
        return $this->messageId;
    }
}
