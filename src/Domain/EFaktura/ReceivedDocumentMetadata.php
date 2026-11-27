<?php

namespace DreaM1\EfakturaConnector\Domain\EFaktura;

use DreaM1\EfakturaConnector\Support\Timestamp;

/** Routing metadata for a document available in a participant's inbox. */
final class ReceivedDocumentMetadata
{
    private ProviderDocumentId $documentId;
    private Participant $sender;
    private Participant $receiver;
    private PeppolDocumentTypeIdentifier $documentTypeId;
    private PeppolProcessIdentifier $processId;
    private Timestamp $creationDateTime;

    /** Restricts construction to factories that validate received document metadata. */
    private function __construct(
        ProviderDocumentId $documentId,
        Participant $sender,
        Participant $receiver,
        PeppolDocumentTypeIdentifier $documentTypeId,
        PeppolProcessIdentifier $processId,
        Timestamp $creationDateTime
    ) {
        $this->documentId       = $documentId;
        $this->sender           = $sender;
        $this->receiver         = $receiver;
        $this->documentTypeId   = $documentTypeId;
        $this->processId        = $processId;
        $this->creationDateTime = $creationDateTime;
    }

    /**
     * Creates validated metadata matching the SAPI DocumentMetadata schema.
     *
     * @param  ProviderDocumentId           $documentId       Provider document identifier.
     * @param  Participant                  $sender           Sender participant.
     * @param  Participant                  $receiver         Receiver participant.
     * @param  PeppolDocumentTypeIdentifier $documentTypeId   Peppol document type identifier.
     * @param  PeppolProcessIdentifier      $processId        Peppol process identifier.
     * @param  Timestamp                    $creationDateTime Creation timestamp reported by the provider.
     * @return self
     */
    public static function create(
        ProviderDocumentId $documentId,
        Participant $sender,
        Participant $receiver,
        PeppolDocumentTypeIdentifier $documentTypeId,
        PeppolProcessIdentifier $processId,
        Timestamp $creationDateTime
    ): self {
        return new self(
            $documentId,
            $sender,
            $receiver,
            $documentTypeId,
            $processId,
            $creationDateTime
        );
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
     * Returns the document type ID.
     *
     * @return PeppolDocumentTypeIdentifier
     */
    public function documentTypeId(): PeppolDocumentTypeIdentifier
    {
        return $this->documentTypeId;
    }

    /**
     * Returns the process ID.
     *
     * @return PeppolProcessIdentifier
     */
    public function processId(): PeppolProcessIdentifier
    {
        return $this->processId;
    }

    /** Returns the provider's document creation timestamp. */
    public function creationDateTime(): Timestamp
    {
        return $this->creationDateTime;
    }

    /**
     * Returns the value as an array for serialization or storage.
     * @return array<string, string> Stable provider-shaped keys for serialization or storage.
     */
    public function toArray(): array
    {
        return [
            'documentId'            => $this->documentId->value(),
            'senderParticipantId'   => $this->sender->identifier(),
            'receiverParticipantId' => $this->receiver->identifier(),
            'documentTypeId'        => $this->documentTypeId->value(),
            'processId'             => $this->processId->value(),
            'creationDateTime'      => $this->creationDateTime->value(),
        ];
    }
}
