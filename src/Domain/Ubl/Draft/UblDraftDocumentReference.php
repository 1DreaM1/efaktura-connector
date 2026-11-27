<?php

namespace DreaM1\EfakturaConnector\Domain\Ubl\Draft;

use DreaM1\EfakturaConnector\Domain\Ubl\CodeList\AdditionalDocumentTypeCode;
use DreaM1\EfakturaConnector\Domain\Ubl\LocalDate;

/**
 * A Peppol Billing reference to a preceding invoice, related document, project, or attachment.
 * The writer maps project references to AdditionalDocumentReference with code 50 for CreditNote.
 */
final class UblDraftDocumentReference
{
    /** Relationship element used for this reference, such as BillingReference or AdditionalDocumentReference. */
    private UblDocumentReferenceType $type;
    /** Referenced document/resource ID and optional schemeID. */
    private UblDraftIdentifier $id;
    /** Referenced invoice issue date, written under BillingReference/InvoiceDocumentReference/IssueDate. */
    private ?LocalDate $issueDate = null;
    /** Optional type code for an AdditionalDocumentReference. */
    private ?AdditionalDocumentTypeCode $documentTypeCode = null;
    /** Optional human-readable description of an additional document. */
    private ?string $description = null;
    /** Optional embedded attachment content written to cbc:EmbeddedDocumentBinaryObject. */
    private ?Base64DocumentContent $embeddedDocument = null;
    /** MIME type written to EmbeddedDocumentBinaryObject/@mimeCode. */
    private ?MimeType $mimeCode = null;
    /** Optional attachment filename written to EmbeddedDocumentBinaryObject/@filename. */
    private ?string $fileName = null;
    /** Optional external attachment address written to cac:ExternalReference/cbc:URI. */
    private ?ExternalDocumentUri $externalUri = null;

    /**
     * Creates a reference using one of this class's supported UBL reference types.
     *
     * @param UblDocumentReferenceType $type UBL reference type such as BILLING or ADDITIONAL.
     * @param UblDraftIdentifier       $id   Identifier and optional scheme assigned to the referenced document or resource.
     */
    public function __construct(UblDocumentReferenceType $type, UblDraftIdentifier $id)
    {
        $this->type = $type;
        $this->id   = $id;
    }

    /**
     * Sets the issue date of the referenced document, when known.
     *
     * @param  LocalDate|null $issueDate Referenced document's issue date, or null when unknown.
     * @return self
     */
    public function withIssueDate(?LocalDate $issueDate): self
    {
        $reference            = clone $this;
        $reference->issueDate = $issueDate;

        return $reference;
    }

    /** Returns a copy with the referenced document or resource identifier. */
    public function withId(UblDraftIdentifier $id): self
    {
        $reference     = clone $this;
        $reference->id = $id;

        return $reference;
    }

    /**
     * Returns a copy with the additional document type code and optional description.
     *
     * @param  AdditionalDocumentTypeCode|null $code        Code describing the additional document, or null to clear it.
     * @param  string|null                     $description Human-readable description of the referenced document.
     * @return self
     */
    public function withDocumentType(?AdditionalDocumentTypeCode $code, ?string $description = null): self
    {
        $reference                   = clone $this;
        $reference->documentTypeCode = $code;
        $reference->description      = UblDraftText::emptyToNull($description);

        return $reference;
    }

    /**
     * Returns a copy with the additional document description; null or blank input clears it.
     *
     * @param  string|null $description Human-readable description of the referenced document.
     * @return self
     */
    public function withDescription(?string $description): self
    {
        $reference              = clone $this;
        $reference->description = UblDraftText::emptyToNull($description);

        return $reference;
    }

    /**
     * Returns a copy with an embedded Base64 attachment and its MIME type and filename.
     *
     * @param  Base64DocumentContent $content  Base64-encoded attachment content.
     * @param  MimeType              $mimeCode MIME type of the attachment.
     * @param  string                $fileName Filename to associate with the attachment.
     * @return self
     */
    public function withEmbeddedDocument(Base64DocumentContent $content, MimeType $mimeCode, string $fileName): self
    {
        $reference                   = clone $this;
        $reference->embeddedDocument = $content;
        $reference->mimeCode         = $mimeCode;
        $reference->fileName         = $fileName;

        return $reference;
    }

    /**
     * Returns a copy with the external attachment URI, or clears it when null.
     *
     * @param  ExternalDocumentUri|null $uri URI locating an external attachment.
     * @return self
     */
    public function withExternalUri(?ExternalDocumentUri $uri): self
    {
        $reference              = clone $this;
        $reference->externalUri = $uri;

        return $reference;
    }

    /** Returns the UBL reference type constant. */
    public function type(): UblDocumentReferenceType
    {
        return $this->type;
    }

    /** Returns the referenced document or resource identifier. */
    public function id(): UblDraftIdentifier
    {
        return $this->id;
    }

    /** Returns the referenced document's issue date, or null when it is unknown. */
    public function issueDate(): ?LocalDate
    {
        return $this->issueDate;
    }

    /** Returns the additional document type code, or null for reference types without one. */
    public function documentTypeCode(): ?AdditionalDocumentTypeCode
    {
        return $this->documentTypeCode;
    }

    /** Returns the human-readable reference description, or null when it is omitted. */
    public function description(): ?string
    {
        return $this->description;
    }

    /** Returns the embedded Base64 content value object, or null when no document is embedded. */
    public function embeddedDocument(): ?Base64DocumentContent
    {
        return $this->embeddedDocument;
    }

    /** Returns the embedded document MIME type, or null when no document is embedded. */
    public function mimeCode(): ?MimeType
    {
        return $this->mimeCode;
    }

    /** Returns the embedded document filename, or null when no document is embedded. */
    public function fileName(): ?string
    {
        return $this->fileName;
    }

    /** Returns the URI of an external attachment, or null when none is configured. */
    public function externalUri(): ?ExternalDocumentUri
    {
        return $this->externalUri;
    }
}
