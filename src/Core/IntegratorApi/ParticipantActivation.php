<?php

namespace DreaM1\EfakturaConnector\Core\IntegratorApi;

use DreaM1\EfakturaConnector\Domain\EFaktura\Participant;
use DreaM1\EfakturaConnector\Support\Timestamp;

/** Provider result for a participant contract activation request. */
final class ParticipantActivation
{
    private ?string $activationId;
    private ?Participant $participantId;
    private ?IntegratorId $integratorId;
    private ?ParticipantContractMode $mode;
    private ?string $status;
    private ?Timestamp $requestedAt;

    /**
     * Creates the provider result for a participant contract activation request.
     *
     * @param string|null                  $activationId  Provider activation identifier.
     * @param Participant|null             $participantId Activated Peppol participant identifier.
     * @param IntegratorId|null            $integratorId  Integrator account identifier.
     * @param ParticipantContractMode|null $mode          Contract mode requested for activation.
     * @param string|null                  $status        Provider activation status.
     * @param Timestamp|null               $requestedAt   Time when the activation was requested.
     */
    public function __construct(
        ?string $activationId,
        ?Participant $participantId,
        ?IntegratorId $integratorId,
        ?ParticipantContractMode $mode,
        ?string $status,
        ?Timestamp $requestedAt
    ) {
        $this->activationId  = $activationId;
        $this->participantId = $participantId;
        $this->integratorId  = $integratorId;
        $this->mode          = $mode;
        $this->status        = $status;
        $this->requestedAt   = $requestedAt;
    }

    /** Returns the provider activation identifier, when supplied. */
    public function activationId(): ?string
    {
        return $this->activationId;
    }

    /** Returns the activated Peppol participant identifier, when supplied. */
    public function participantId(): ?Participant
    {
        return $this->participantId;
    }

    /** Returns the integrator account identifier, when supplied. */
    public function integratorId(): ?IntegratorId
    {
        return $this->integratorId;
    }

    /** Returns the requested contract mode, when supplied. */
    public function mode(): ?ParticipantContractMode
    {
        return $this->mode;
    }

    /** Returns the provider activation status, when supplied. */
    public function status(): ?string
    {
        return $this->status;
    }

    /** Returns when activation was requested, when supplied. */
    public function requestedAt(): ?Timestamp
    {
        return $this->requestedAt;
    }
}
