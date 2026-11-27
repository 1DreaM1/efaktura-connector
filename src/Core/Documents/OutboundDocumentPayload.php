<?php

namespace DreaM1\EfakturaConnector\Core\Documents;

use DreaM1\EfakturaConnector\Domain\EFaktura\CodeList\ParticipantIdentifierSchemeCodeList;
use DreaM1\EfakturaConnector\Domain\EFaktura\EFakturaDocumentLimits;
use DreaM1\EfakturaConnector\Domain\EFaktura\Participant;
use DreaM1\EfakturaConnector\Domain\EFaktura\PeppolDocumentTypeIdentifier;
use DreaM1\EfakturaConnector\Domain\EFaktura\PeppolProcessIdentifier;
use DreaM1\EfakturaConnector\Domain\EFaktura\SubmittedDocumentId;
use DreaM1\EfakturaConnector\Support\Exceptions\InvalidInputException;
use DreaM1\EfakturaConnector\Support\Timestamp;

/** Immutable SAPI submission data, including the UBL XML and an optional checksum. */
final class OutboundDocumentPayload
{
    private SubmittedDocumentId $documentId;
    private PeppolDocumentTypeIdentifier $documentTypeId;
    private PeppolProcessIdentifier $processId;
    private Participant $sender;
    private Participant $receiver;
    private Timestamp $creationTimestamp;
    private string $xml;
    private ?Sha256Checksum $checksum;

    /**
     * Creates the routing metadata and UBL content for an outbound SAPI submission.
     *
     * @param  SubmittedDocumentId          $documentId       Sender-assigned identifier used to identify this document in the exchange.
     * @param  PeppolDocumentTypeIdentifier $documentTypeId   Peppol identifier for the type of UBL document being sent.
     * @param  PeppolProcessIdentifier      $processId        Peppol business process identifier for this document exchange.
     * @param  Participant                  $sender           Participant sending the document.
     * @param  Participant                  $receiver         Intended recipient of the document.
     * @param  Timestamp                    $creationDateTime Time the payload was created.
     * @param  string                       $xml              UBL XML content of the invoice or credit note being sent.
     * @param  Sha256Checksum|null          $checksum         Optional SHA-256 checksum of the XML content.
     * @throws InvalidInputException        when XML exceeds the SAPI limit, either participant scheme is not allowed,
     *                                      or the supplied checksum does not match the XML.
     */
    public function __construct(
        SubmittedDocumentId $documentId,
        PeppolDocumentTypeIdentifier $documentTypeId,
        PeppolProcessIdentifier $processId,
        Participant $sender,
        Participant $receiver,
        Timestamp $creationDateTime,
        string $xml,
        ?Sha256Checksum $checksum = null
    ) {
        if (strlen($xml) > EFakturaDocumentLimits::MAX_XML_SIZE_BYTES) {
            throw new InvalidInputException('Document payload exceeds the SAPI size limit.');
        }

        if (
            ! ParticipantIdentifierSchemeCodeList::isAllowedForNewDocument($sender->scheme())
            || ! ParticipantIdentifierSchemeCodeList::isAllowedForNewDocument($receiver->scheme())
        ) {
            throw new InvalidInputException(
                'Sender and receiver must use active, registrable Peppol participant identifier schemes.'
            );
        }

        if ($checksum !== null && ! $checksum->matchesContent($xml)) {
            throw new InvalidInputException('Checksum must be the lowercase SHA-256 hash of the XML payload.');
        }

        $this->documentId        = $documentId;
        $this->documentTypeId    = $documentTypeId;
        $this->processId         = $processId;
        $this->sender            = $sender;
        $this->receiver          = $receiver;
        $this->creationTimestamp = $creationDateTime;
        $this->xml               = $xml;
        $this->checksum          = $checksum;
    }

    /**
     * Returns the sender-assigned identifier used to identify this document in the exchange.
     *
     * @return SubmittedDocumentId
     */
    public function documentId(): SubmittedDocumentId
    {
        return $this->documentId;
    }

    /**
     * Returns the Peppol identifier for the type of UBL document being sent.
     *
     * @return PeppolDocumentTypeIdentifier
     */
    public function documentTypeId(): PeppolDocumentTypeIdentifier
    {
        return $this->documentTypeId;
    }

    /**
     * Returns the Peppol business process identifier for this document exchange.
     *
     * @return PeppolProcessIdentifier
     */
    public function processId(): PeppolProcessIdentifier
    {
        return $this->processId;
    }

    /**
     * The sending participant; the provider is called on its behalf.
     *
     * @return Participant
     */
    public function sender(): Participant
    {
        return $this->sender;
    }

    /**
     * The receiving participant.
     *
     * @return Participant
     */
    public function receiver(): Participant
    {
        return $this->receiver;
    }

    /**
     * Returns the time the payload was created.
     */
    public function creationDateTime(): Timestamp
    {
        return $this->creationTimestamp;
    }

    /**
     * Returns the UBL XML content of the invoice or credit note being sent.
     *
     * @return string
     */
    public function xml(): string
    {
        return $this->xml;
    }

    /**
     * Returns the optional SHA-256 checksum of the XML content.
     *
     * @return Sha256Checksum|null
     */
    public function checksum(): ?Sha256Checksum
    {
        return $this->checksum;
    }
}
