<?php

namespace DreaM1\EfakturaConnector\Core\IntegratorApi;

use DreaM1\EfakturaConnector\Core\Documents\IdempotencyKey;
use DreaM1\EfakturaConnector\Domain\EFaktura\Participant;

/** Request to activate one participant's contract with the selected access mode. */
final class ActivateParticipantRequest
{
    private Participant $participantId;
    private ParticipantContractMode $mode;
    private ?string $requestedByUserId;
    private IdempotencyKey $idempotencyKey;

    /**
     * Creates a participant activation request with a supported contract mode.
     *
     * @param Participant             $participantId     Peppol participant identifier.
     * @param ParticipantContractMode $mode              Contract mode requested for the participant.
     * @param IdempotencyKey          $idempotencyKey    Key used to safely identify a repeated request.
     * @param string|null             $requestedByUserId Optional identifier of the user requesting activation.
     */
    public function __construct(
        Participant $participantId,
        ParticipantContractMode $mode,
        IdempotencyKey $idempotencyKey,
        ?string $requestedByUserId = null
    ) {
        $this->participantId     = $participantId;
        $this->mode              = $mode;
        $this->requestedByUserId = $requestedByUserId;
        $this->idempotencyKey    = $idempotencyKey;
    }

    /** Returns the Peppol participant identifier. */
    public function participantId(): Participant
    {
        return $this->participantId;
    }

    /** Returns the key used to identify a repeated activation request. */
    public function idempotencyKey(): IdempotencyKey
    {
        return $this->idempotencyKey;
    }

    /** Returns the contract mode requested for the participant. */
    public function mode(): ParticipantContractMode
    {
        return $this->mode;
    }

    /** Returns the optional identifier of the user requesting activation. */
    public function requestedByUserId(): ?string
    {
        return $this->requestedByUserId;
    }
}
