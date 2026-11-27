<?php

namespace DreaM1\EfakturaConnector\Core\Documents;

use DreaM1\EfakturaConnector\Domain\EFaktura\PeppolDocumentTypeIdentifier;
use DreaM1\EfakturaConnector\Domain\EFaktura\PeppolProcessIdentifier;
use DreaM1\EfakturaConnector\Domain\EFaktura\ProviderDocumentId;
use DreaM1\EfakturaConnector\Domain\EFaktura\SubmittedDocumentId;

/** Identifiers and format of a submitted document; each is null when the provider omitted it. */
final class SentDocumentIdentity
{
    private ?ProviderDocumentId $identifier;
    private ?SubmittedDocumentId $documentId;
    private ?PeppolDocumentTypeIdentifier $documentTypeId;
    private ?PeppolProcessIdentifier $processId;
    private ?string $payloadFormat;

    /**
     * Creates the identity of a submitted document.
     *
     * @param ProviderDocumentId|null           $identifier     Provider tracking identifier.
     * @param SubmittedDocumentId|null          $documentId     Document identifier supplied with the submission.
     * @param PeppolDocumentTypeIdentifier|null $documentTypeId Peppol document type identifier.
     * @param PeppolProcessIdentifier|null      $processId      Peppol process identifier.
     * @param string|null                       $payloadFormat  Format reported for the submitted payload.
     */
    public function __construct(
        ?ProviderDocumentId $identifier = null,
        ?SubmittedDocumentId $documentId = null,
        ?PeppolDocumentTypeIdentifier $documentTypeId = null,
        ?PeppolProcessIdentifier $processId = null,
        ?string $payloadFormat = null
    ) {
        $this->identifier     = $identifier;
        $this->documentId     = $documentId;
        $this->documentTypeId = $documentTypeId;
        $this->processId      = $processId;
        $this->payloadFormat  = $payloadFormat;
    }

    /** Returns the provider tracking identifier, or null when omitted. */
    public function identifier(): ?ProviderDocumentId
    {
        return $this->identifier;
    }

    /** Returns the sender-assigned submitted document identifier, or null when omitted. */
    public function documentId(): ?SubmittedDocumentId
    {
        return $this->documentId;
    }

    /** Returns the Peppol document type identifier, or null when omitted. */
    public function documentTypeId(): ?PeppolDocumentTypeIdentifier
    {
        return $this->documentTypeId;
    }

    /** Returns the Peppol process identifier, or null when omitted. */
    public function processId(): ?PeppolProcessIdentifier
    {
        return $this->processId;
    }

    /** Returns the submitted payload format, or null when omitted. */
    public function payloadFormat(): ?string
    {
        return $this->payloadFormat;
    }
}
