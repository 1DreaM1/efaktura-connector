<?php

namespace DreaM1\EfakturaConnector\Core\Documents;

use DreaM1\EfakturaConnector\Domain\EFaktura\Participant;
use DreaM1\EfakturaConnector\Support\Exceptions\InvalidInputException;

/** Input for checking an outbound document with the Peppol Gateway. */
final class ValidateDocumentRequest
{
    private Participant $participant;
    private OutboundDocumentPayload $payload;

    /**
     * Creates a participant-scoped document validation request.
     *
     * @param  Participant             $participant Participant identity used to scope the validation.
     * @param  OutboundDocumentPayload $payload     Document type, process and XML to validate.
     * @throws InvalidInputException   when the participant differs from the payload sender
     */
    public function __construct(Participant $participant, OutboundDocumentPayload $payload)
    {
        if (! $participant->equals($payload->sender())) {
            throw new InvalidInputException('Validation participant must match the payload sender.');
        }

        $this->participant = $participant;
        $this->payload     = $payload;
    }

    /** Returns the participant on whose behalf the document is validated. */
    public function participant(): Participant
    {
        return $this->participant;
    }

    /** Returns the prepared payload to validate. */
    public function payload(): OutboundDocumentPayload
    {
        return $this->payload;
    }
}
