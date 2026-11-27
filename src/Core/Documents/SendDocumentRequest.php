<?php

namespace DreaM1\EfakturaConnector\Core\Documents;

use DreaM1\EfakturaConnector\Domain\EFaktura\Participant;
use DreaM1\EfakturaConnector\Support\Exceptions\InvalidInputException;

/** Input for submitting one outbound document through SAPI. */
final class SendDocumentRequest
{
    private Participant $participant;
    private OutboundDocumentPayload $payload;
    private IdempotencyKey $idempotencyKey;

    /**
     * Creates a participant-scoped submission request with a stable idempotency key.
     *
     * @param  Participant             $participant    Participant identity used to scope the submission.
     * @param  OutboundDocumentPayload $payload        Document type, process and XML to submit.
     * @param  IdempotencyKey          $idempotencyKey Stable key reused for retries of this logical submission.
     * @throws InvalidInputException   when the participant differs from the payload sender
     */
    public function __construct(
        Participant $participant,
        OutboundDocumentPayload $payload,
        IdempotencyKey $idempotencyKey
    ) {
        if (! $participant->equals($payload->sender())) {
            throw new InvalidInputException('Submission participant must match the payload sender.');
        }

        $this->participant    = $participant;
        $this->payload        = $payload;
        $this->idempotencyKey = $idempotencyKey;
    }

    /** Returns the participant on whose behalf the document is submitted. */
    public function participant(): Participant
    {
        return $this->participant;
    }

    /** Returns the prepared payload to submit. */
    public function payload(): OutboundDocumentPayload
    {
        return $this->payload;
    }

    /** Returns the stable key identifying this logical submission. */
    public function idempotencyKey(): IdempotencyKey
    {
        return $this->idempotencyKey;
    }
}
