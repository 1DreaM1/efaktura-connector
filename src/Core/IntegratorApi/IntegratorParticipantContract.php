<?php

namespace DreaM1\EfakturaConnector\Core\IntegratorApi;

/** Contract and ownership state of an Integrator participant; each value is null when the provider omitted it. */
final class IntegratorParticipantContract
{
    private ?bool $internalTransferRequired;
    private ?bool $isOwner;
    private ?ParticipantContractMode $activeContractMode;

    /**
     * Creates the contract state of one participant.
     *
     * @param bool|null                    $internalTransferRequired Whether internal transfer is required.
     * @param bool|null                    $isOwner                  Whether the participant owns the integrator account.
     * @param ParticipantContractMode|null $activeContractMode       Current supported contract mode, when available.
     */
    public function __construct(
        ?bool $internalTransferRequired = null,
        ?bool $isOwner = null,
        ?ParticipantContractMode $activeContractMode = null
    ) {
        $this->internalTransferRequired = $internalTransferRequired;
        $this->isOwner                  = $isOwner;
        $this->activeContractMode       = $activeContractMode;
    }

    /** Returns whether internal transfer is required, when supplied by the provider. */
    public function internalTransferRequired(): ?bool
    {
        return $this->internalTransferRequired;
    }

    /** Returns whether the participant owns the integrator account, when supplied. */
    public function isOwner(): ?bool
    {
        return $this->isOwner;
    }

    /** Returns the active supported contract mode, when one is available. */
    public function activeContractMode(): ?ParticipantContractMode
    {
        return $this->activeContractMode;
    }
}
