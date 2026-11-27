<?php

namespace DreaM1\EfakturaConnector\Core\IntegratorApi;

use DreaM1\EfakturaConnector\Domain\EFaktura\Participant;
use DreaM1\EfakturaConnector\Support\Timestamp;

/** Participant onboarding and contract metadata returned by the Integrator API. */
final class IntegratorParticipant
{
    private ?Participant $participantId;
    private IntegratorParticipantCompany $company;
    private IntegratorParticipantTimestamps $timestamps;
    private IntegratorParticipantContract $contract;

    /**
     * Creates a typed representation of participant onboarding and contract metadata.
     *
     * @param Participant|null                $participantId Peppol participant identifier.
     * @param IntegratorParticipantCompany    $company       Tax identifier, legal name and contact details.
     * @param IntegratorParticipantTimestamps $timestamps    Source creation and provider receipt times.
     * @param IntegratorParticipantContract   $contract      Transfer, ownership and contract mode state.
     */
    public function __construct(
        ?Participant $participantId,
        IntegratorParticipantCompany $company,
        IntegratorParticipantTimestamps $timestamps,
        IntegratorParticipantContract $contract
    ) {
        $this->participantId = $participantId;
        $this->company       = $company;
        $this->timestamps    = $timestamps;
        $this->contract      = $contract;
    }

    /** Returns the Peppol participant identifier, when available. */
    public function participantId(): ?Participant
    {
        return $this->participantId;
    }

    /** Returns the tax identifier, legal name and contact details. */
    public function company(): IntegratorParticipantCompany
    {
        return $this->company;
    }

    /** Returns the source creation and provider receipt times. */
    public function timestamps(): IntegratorParticipantTimestamps
    {
        return $this->timestamps;
    }

    /** Returns the transfer, ownership and contract mode state. */
    public function contract(): IntegratorParticipantContract
    {
        return $this->contract;
    }

    /** Returns the participant tax identifier, when available. */
    public function dic(): ?string
    {
        return $this->company->dic();
    }

    /** Returns the participant legal name, when available. */
    public function legalName(): ?string
    {
        return $this->company->legalName();
    }

    /** Returns the participant contact email, when available. */
    public function companyEmail(): ?string
    {
        return $this->company->email();
    }

    /** Returns the participant contact phone, when available. */
    public function companyPhone(): ?string
    {
        return $this->company->phone();
    }

    /** Returns the source system creation time, when supplied by the provider. */
    public function sourceCreatedAt(): ?Timestamp
    {
        return $this->timestamps->sourceCreatedAt();
    }

    /** Returns when the provider received the participant, when supplied. */
    public function receivedAt(): ?Timestamp
    {
        return $this->timestamps->receivedAt();
    }

    /** Returns whether internal transfer is required, when supplied by the provider. */
    public function internalTransferRequired(): ?bool
    {
        return $this->contract->internalTransferRequired();
    }

    /** Returns whether the participant owns the integrator account, when supplied. */
    public function isOwner(): ?bool
    {
        return $this->contract->isOwner();
    }

    /** Returns the active supported contract mode, when one is available. */
    public function activeContractMode(): ?ParticipantContractMode
    {
        return $this->contract->activeContractMode();
    }
}
