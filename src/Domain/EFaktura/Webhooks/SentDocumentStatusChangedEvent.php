<?php

namespace DreaM1\EfakturaConnector\Domain\EFaktura\Webhooks;

use DreaM1\EfakturaConnector\Domain\EFaktura\Participant;
use DreaM1\EfakturaConnector\Domain\EFaktura\ProviderDocumentId;
use DreaM1\EfakturaConnector\Domain\EFaktura\ProviderDocumentStatus;
use DreaM1\EfakturaConnector\Support\Exceptions\InvalidInputException;
use DreaM1\EfakturaConnector\Support\NonBlankString;

/** Validated domain event for a provider change to an outbound document's status. */
final class SentDocumentStatusChangedEvent
{
    private string $identifier;
    private ProviderDocumentId $documentId;
    private Participant $sender;
    private Participant $receiver;
    private ProviderDocumentStatus $status;
    private bool $hasError;

    /** Restricts construction to the provider event parsing factory. */
    private function __construct(
        string $identifier,
        ProviderDocumentId $documentId,
        Participant $sender,
        Participant $receiver,
        ProviderDocumentStatus $status,
        bool $hasError
    ) {
        $this->identifier = $identifier;
        $this->documentId = $documentId;
        $this->sender     = $sender;
        $this->receiver   = $receiver;
        $this->status     = $status;
        $this->hasError   = $hasError;
    }

    /**
     * Creates an event with a non-empty identifier and an outbound status when the status is known.
     *
     * @param  string                 $identifier Identifier of the provider's sent-document record.
     * @param  ProviderDocumentId     $documentId Provider document identifier.
     * @param  Participant            $sender     Sender participant.
     * @param  Participant            $receiver   Receiver participant.
     * @param  ProviderDocumentStatus $status     Provider-reported document status.
     * @param  bool                   $hasError   Whether the result contains an error.
     * @return self
     * @throws InvalidInputException  when required metadata is blank or a known status is not outbound
     */
    public static function create(
        string $identifier,
        ProviderDocumentId $documentId,
        Participant $sender,
        Participant $receiver,
        ProviderDocumentStatus $status,
        bool $hasError
    ): self {
        NonBlankString::assertNotBlank(
            $identifier,
            'Webhook status-change event metadata must not be empty.'
        );

        if ($status->isKnown() && ! $status->isOutboundStatus()) {
            throw new InvalidInputException('Event contains an unsupported document status.');
        }

        return new self($identifier, $documentId, $sender, $receiver, $status, $hasError);
    }

    /**
     * Returns the identifier.
     *
     * @return string
     */
    public function identifier(): string
    {
        return $this->identifier;
    }

    /**
     * Returns the document ID.
     *
     * @return ProviderDocumentId
     */
    public function documentId(): ProviderDocumentId
    {
        return $this->documentId;
    }

    /** Returns the sender participant. */
    public function sender(): Participant
    {
        return $this->sender;
    }

    /** Returns the receiver participant. */
    public function receiver(): Participant
    {
        return $this->receiver;
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
     * Whether the provider reported an error for the document.
     *
     * @return bool
     */
    public function hasError(): bool
    {
        return $this->hasError;
    }
}
