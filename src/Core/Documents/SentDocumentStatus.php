<?php

namespace DreaM1\EfakturaConnector\Core\Documents;

use DreaM1\EfakturaConnector\Domain\EFaktura\Participant;
use DreaM1\EfakturaConnector\Domain\EFaktura\PeppolDocumentTypeIdentifier;
use DreaM1\EfakturaConnector\Domain\EFaktura\PeppolProcessIdentifier;
use DreaM1\EfakturaConnector\Domain\EFaktura\ProviderDocumentId;
use DreaM1\EfakturaConnector\Domain\EFaktura\ProviderDocumentStatus;
use DreaM1\EfakturaConnector\Domain\EFaktura\SubmittedDocumentId;
use DreaM1\EfakturaConnector\Support\Timestamp;

/** Provider status and participant metadata for a submitted document. */
final class SentDocumentStatus
{
    private ?ProviderDocumentStatus $status;
    private SentDocumentParties $parties;
    private SentDocumentTiming $timing;
    private SentDocumentIdentity $identity;
    private SentDocumentOutcome $outcome;

    /** Restricts construction to the named status factory. */
    private function __construct(
        ?ProviderDocumentStatus $status,
        SentDocumentParties $parties,
        SentDocumentTiming $timing,
        SentDocumentIdentity $identity,
        SentDocumentOutcome $outcome
    ) {
        $this->status   = $status;
        $this->parties  = $parties;
        $this->timing   = $timing;
        $this->identity = $identity;
        $this->outcome  = $outcome;
    }

    /**
     * Creates a sent-document status from provider response fields.
     *
     * @param  ProviderDocumentStatus|null $status   Provider-reported delivery status.
     * @param  SentDocumentParties         $parties  Sender and receiver participants.
     * @param  SentDocumentTiming          $timing   Provider creation and last-update timestamps.
     * @param  SentDocumentIdentity        $identity Provider and Peppol identifiers and payload format.
     * @param  SentDocumentOutcome         $outcome  Downstream provider references and error details.
     * @return self                        Immutable sent-document status value.
     */
    public static function create(
        ?ProviderDocumentStatus $status,
        SentDocumentParties $parties,
        SentDocumentTiming $timing,
        SentDocumentIdentity $identity,
        SentDocumentOutcome $outcome
    ): self {
        return new self($status, $parties, $timing, $identity, $outcome);
    }

    /**
     * Returns the status, or null when the provider omitted this optional response field.
     *
     * @return ProviderDocumentStatus|null
     */
    public function status(): ?ProviderDocumentStatus
    {
        return $this->status;
    }

    /** Returns the sender and receiver participants. */
    public function parties(): SentDocumentParties
    {
        return $this->parties;
    }

    /** Returns the provider creation and last-update timestamps. */
    public function timing(): SentDocumentTiming
    {
        return $this->timing;
    }

    /** Returns the provider and Peppol identifiers and the payload format. */
    public function identity(): SentDocumentIdentity
    {
        return $this->identity;
    }

    /** Returns the downstream provider references and error details. */
    public function outcome(): SentDocumentOutcome
    {
        return $this->outcome;
    }

    /** Returns the sender participant, or null when unavailable. */
    public function sender(): ?Participant
    {
        return $this->parties->sender();
    }

    /** Returns the receiver participant, or null when unavailable. */
    public function receiver(): ?Participant
    {
        return $this->parties->receiver();
    }

    /** Returns the provider's last-update timestamp, or null when unavailable. */
    public function updatedAt(): ?Timestamp
    {
        return $this->timing->updatedAt();
    }

    /** Returns the provider creation timestamp, or null when omitted. */
    public function createdAt(): ?Timestamp
    {
        return $this->timing->createdAt();
    }

    /** Returns the provider tracking identifier, or null when omitted. */
    public function identifier(): ?ProviderDocumentId
    {
        return $this->identity->identifier();
    }

    /** Returns the sender-assigned submitted document identifier, or null when omitted. */
    public function documentId(): ?SubmittedDocumentId
    {
        return $this->identity->documentId();
    }

    /** Returns the Peppol document type identifier, or null when omitted. */
    public function documentTypeId(): ?PeppolDocumentTypeIdentifier
    {
        return $this->identity->documentTypeId();
    }

    /** Returns the Peppol process identifier, or null when omitted. */
    public function processId(): ?PeppolProcessIdentifier
    {
        return $this->identity->processId();
    }

    /** Returns the submitted payload format, or null when omitted. */
    public function payloadFormat(): ?string
    {
        return $this->identity->payloadFormat();
    }

    /** Returns downstream provider identifiers, or null when omitted. */
    public function provider(): ?SentDocumentProviderReference
    {
        return $this->outcome->provider();
    }

    /** Returns provider error details, or null when the response has no error. */
    public function error(): ?SentDocumentError
    {
        return $this->outcome->error();
    }
}
