<?php

namespace DreaM1\EfakturaConnector\Infrastructure\EFaktura\Documents;

use DOMException;
use DreaM1\EfakturaConnector\Core\Contracts\DocumentSubmissionGatewayInterface;
use DreaM1\EfakturaConnector\Core\Contracts\UblDraftSubmissionInterface;
use DreaM1\EfakturaConnector\Core\Documents\DocumentSubmissionResult;
use DreaM1\EfakturaConnector\Core\Documents\IdempotencyKey;
use DreaM1\EfakturaConnector\Core\Documents\OutboundDocumentPayload;
use DreaM1\EfakturaConnector\Core\Documents\SendDocumentRequest;
use DreaM1\EfakturaConnector\Core\Documents\Sha256Checksum;
use DreaM1\EfakturaConnector\Core\Exceptions\ProviderApiException;
use DreaM1\EfakturaConnector\Core\Exceptions\RequestCoordinationException;
use DreaM1\EfakturaConnector\Core\Exceptions\RequestEncodingException;
use DreaM1\EfakturaConnector\Core\Ubl\Contracts\UblDocumentWriterInterface;
use DreaM1\EfakturaConnector\Core\Ubl\PeppolDocumentTypeIdentifierBuilder;
use DreaM1\EfakturaConnector\Domain\EFaktura\Participant;
use DreaM1\EfakturaConnector\Domain\EFaktura\SubmittedDocumentId;
use DreaM1\EfakturaConnector\Domain\Ubl\Draft\UblDocumentDraft;
use DreaM1\EfakturaConnector\Support\Exceptions\InvalidInputException;
use DreaM1\EfakturaConnector\Support\Timestamp;

/**
 * Prepares and submits a UBL invoice or credit note draft.
 *
 * @api
 */
final class UblDraftSubmission implements UblDraftSubmissionInterface
{
    private UblDocumentWriterInterface $writer;
    private DocumentSubmissionGatewayInterface $gateway;

    /**
     * @param UblDocumentWriterInterface         $writer  UBL writer used to serialize the document.
     * @param DocumentSubmissionGatewayInterface $gateway Gateway adapter used for the provider operation.
     */
    public function __construct(
        UblDocumentWriterInterface $writer,
        DocumentSubmissionGatewayInterface $gateway
    ) {
        $this->writer  = $writer;
        $this->gateway = $gateway;
    }

    /**
     * Prepares the draft and sends it through the existing document submission gateway.
     *
     * @param  Participant                  $sender           Sender identity and participant context for the provider request.
     * @param  Participant                  $receiver         Intended recipient's participant identity.
     * @param  UblDocumentDraft             $draft            UBL document draft to serialize or submit.
     * @param  Timestamp                    $creationDateTime Timestamp assigned to this submission attempt.
     * @param  IdempotencyKey               $idempotencyKey   Stable key retained by the host for this attempt.
     * @return DocumentSubmissionResult
     * @throws DOMException                 when a DOM operation fails while serializing the UBL draft.
     * @throws InvalidInputException        when the draft cannot be serialized into a valid payload or exceeds the provider limit.
     * @throws ProviderApiException         when the provider operation fails or is deferred.
     * @throws RequestEncodingException     when the API request body cannot be encoded.
     * @throws RequestCoordinationException when local request
     *                                      coordination cannot be established
     */
    public function send(
        Participant $sender,
        Participant $receiver,
        UblDocumentDraft $draft,
        Timestamp $creationDateTime,
        IdempotencyKey $idempotencyKey
    ): DocumentSubmissionResult {
        $payload = $this->preparePayload($sender, $receiver, $draft, $creationDateTime);

        return $this->gateway->send(new SendDocumentRequest($sender, $payload, $idempotencyKey));
    }

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
    ): OutboundDocumentPayload {
        $xml = $this->writer->write($draft);

        $typeId = PeppolDocumentTypeIdentifierBuilder::forDocument(
            $draft->kind()->value(),
            $draft->customizationId()->value()
        );

        $checksum = Sha256Checksum::fromContent($xml);

        // Use transport participant identities supplied by the caller; UBL EndpointID is a separate identifier.
        return new OutboundDocumentPayload(
            SubmittedDocumentId::fromString($draft->number()->value()),
            $typeId,
            $draft->profileId(),
            $sender,
            $receiver,
            $creationDateTime,
            $xml,
            $checksum
        );
    }
}
