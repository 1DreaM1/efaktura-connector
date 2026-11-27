<?php

namespace DreaM1\EfakturaConnector\Core\MessageStatuses;

use DreaM1\EfakturaConnector\Core\Documents\SentDocumentParties;
use DreaM1\EfakturaConnector\Domain\EFaktura\MessageLevelStatusCode;
use DreaM1\EfakturaConnector\Domain\EFaktura\MessageLevelStatusIdentifier;
use DreaM1\EfakturaConnector\Domain\EFaktura\Participant;
use DreaM1\EfakturaConnector\Domain\EFaktura\PeppolDocumentTypeIdentifier;
use DreaM1\EfakturaConnector\Domain\EFaktura\ProviderMessageId;
use DreaM1\EfakturaConnector\Support\Timestamp;

/** One provider message-level status; identifier and response code are optional in Gateway responses. */
final class ProviderMessageLevelStatus
{
    private ?MessageLevelStatusIdentifier $identifier;
    private ?ProviderMessageId $messageId;
    private ProviderMessageLevelStatusResult $result;
    private SentDocumentParties $parties;
    private ?PeppolDocumentTypeIdentifier $documentTypeId;
    private ?Timestamp $receivedAt;

    /** Restricts construction to the named factory. */
    private function __construct(
        ?MessageLevelStatusIdentifier $identifier,
        ?ProviderMessageId $messageId,
        ProviderMessageLevelStatusResult $result,
        SentDocumentParties $parties,
        ?PeppolDocumentTypeIdentifier $documentTypeId,
        ?Timestamp $receivedAt
    ) {
        $this->identifier     = $identifier;
        $this->messageId      = $messageId;
        $this->result         = $result;
        $this->parties        = $parties;
        $this->documentTypeId = $documentTypeId;
        $this->receivedAt     = $receivedAt;
    }

    /**
     * Creates a value object from the supplied fields.
     *
     * @param  MessageLevelStatusIdentifier|null $identifier     Provider identifier for this status record.
     * @param  ProviderMessageId|null            $messageId      Provider message identifier.
     * @param  ProviderMessageLevelStatusResult  $result         Response code, description and reasons.
     * @param  SentDocumentParties               $parties        Sender and receiver participants.
     * @param  PeppolDocumentTypeIdentifier|null $documentTypeId Peppol document type identifier.
     * @param  Timestamp|null                    $receivedAt     Timestamp when the provider received the document.
     * @return self
     */
    public static function create(
        ?MessageLevelStatusIdentifier $identifier,
        ?ProviderMessageId $messageId,
        ProviderMessageLevelStatusResult $result,
        SentDocumentParties $parties,
        ?PeppolDocumentTypeIdentifier $documentTypeId = null,
        ?Timestamp $receivedAt = null
    ): self {
        return new self($identifier, $messageId, $result, $parties, $documentTypeId, $receivedAt);
    }

    /**
     * Returns the identifier.
     *
     * @return MessageLevelStatusIdentifier|null
     */
    public function identifier(): ?MessageLevelStatusIdentifier
    {
        return $this->identifier;
    }

    /** Returns the provider's identifier for the Peppol message, when supplied. */
    public function messageId(): ?ProviderMessageId
    {
        return $this->messageId;
    }

    /** Returns the response code, description and reasons. */
    public function result(): ProviderMessageLevelStatusResult
    {
        return $this->result;
    }

    /** Returns the sender and receiver participants. */
    public function parties(): SentDocumentParties
    {
        return $this->parties;
    }

    /** Returns the Peppol document type identifier, when supplied. */
    public function documentTypeId(): ?PeppolDocumentTypeIdentifier
    {
        return $this->documentTypeId;
    }

    /**
     * Returns the timestamp at which the provider received the message.
     *
     * @return Timestamp|null
     */
    public function receivedAt(): ?Timestamp
    {
        return $this->receivedAt;
    }

    /**
     * Returns the response code.
     *
     * @return MessageLevelStatusCode|null
     */
    public function responseCode(): ?MessageLevelStatusCode
    {
        return $this->result->code();
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

    /**
     * Returns the response description.
     *
     * @return string|null
     */
    public function responseDescription(): ?string
    {
        return $this->result->description();
    }

    /**
     * Returns the reasons.
     *
     * @return list<ProviderMessageLevelStatusReason>
     */
    public function reasons(): array
    {
        return $this->result->reasons();
    }
}
