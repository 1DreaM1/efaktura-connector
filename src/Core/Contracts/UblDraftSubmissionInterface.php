<?php

namespace DreaM1\EfakturaConnector\Core\Contracts;

use DOMException;
use DreaM1\EfakturaConnector\Core\Documents\DocumentSubmissionResult;
use DreaM1\EfakturaConnector\Core\Documents\IdempotencyKey;
use DreaM1\EfakturaConnector\Core\Documents\OutboundDocumentPayload;
use DreaM1\EfakturaConnector\Core\Exceptions\ProviderApiException;
use DreaM1\EfakturaConnector\Core\Exceptions\RequestCoordinationException;
use DreaM1\EfakturaConnector\Core\Exceptions\RequestEncodingException;
use DreaM1\EfakturaConnector\Domain\EFaktura\Participant;
use DreaM1\EfakturaConnector\Domain\Ubl\Draft\UblDocumentDraft;
use DreaM1\EfakturaConnector\Support\Exceptions\InvalidInputException;
use DreaM1\EfakturaConnector\Support\Timestamp;

/** Prepares UBL drafts for submission and sends them through the configured provider gateway. */
interface UblDraftSubmissionInterface
{
    /**
     * Serializes a draft, builds its submission payload and sends it through the configured gateway.
     *
     * @param  Participant                  $sender           Sender identity and participant context for the provider request.
     * @param  Participant                  $receiver         Intended recipient's participant identity.
     * @param  UblDocumentDraft             $draft            UBL document draft to serialize and submit.
     * @param  Timestamp                    $creationDateTime Timestamp assigned to this submission attempt.
     * @param  IdempotencyKey               $idempotencyKey   Stable key retained by the host for this attempt.
     * @return DocumentSubmissionResult
     * @throws DOMException                 when a DOM operation fails while serializing the UBL draft.
     * @throws ProviderApiException         when the provider operation fails or is deferred.
     * @throws RequestEncodingException     when the API request body cannot be encoded.
     * @throws InvalidInputException        when the draft cannot be serialized into a valid payload or exceeds the provider limit.
     * @throws RequestCoordinationException when local request
     *                                      coordination cannot be established
     */
    public function send(
        Participant $sender,
        Participant $receiver,
        UblDocumentDraft $draft,
        Timestamp $creationDateTime,
        IdempotencyKey $idempotencyKey
    ): DocumentSubmissionResult;

    /**
     * Converts a UBL draft into the payload used by submission and document validation.
     *
     * Participant routing identifiers are supplied separately because they are not reliably
     * derivable from the UBL parties' electronic endpoint identifiers.
     *
     * @param  Participant             $sender           Sender's Peppol participant identity for the transport operation.
     * @param  Participant             $receiver         Receiver's Peppol participant identity for the transport operation.
     * @param  UblDocumentDraft        $draft            UBL document draft to serialize or submit.
     * @param  Timestamp               $creationDateTime Timestamp assigned to this submission attempt.
     * @return OutboundDocumentPayload
     * @throws DOMException            when a DOM operation fails while serializing the UBL draft.
     * @throws InvalidInputException   when the draft cannot be serialized into a valid payload or exceeds the provider limit.
     */
    public function preparePayload(
        Participant $sender,
        Participant $receiver,
        UblDocumentDraft $draft,
        Timestamp $creationDateTime
    ): OutboundDocumentPayload;
}
