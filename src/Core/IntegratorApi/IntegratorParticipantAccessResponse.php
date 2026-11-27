<?php

namespace DreaM1\EfakturaConnector\Core\IntegratorApi;

use DreaM1\EfakturaConnector\Domain\EFaktura\Participant;

/** Participant permissions returned in an OAuth client response. */
final class IntegratorParticipantAccessResponse
{
    private ?Participant $participantId;
    private ?bool $canSend;
    private ?bool $canReceive;

    /**
     * Creates a permission value from fields returned by the provider.
     *
     * @param Participant|null $participantId Peppol participant identifier, when present.
     * @param bool|null        $canSend       Whether sending is allowed, when reported.
     * @param bool|null        $canReceive    Whether receiving is allowed, when reported.
     */
    public function __construct(?Participant $participantId, ?bool $canSend, ?bool $canReceive)
    {
        $this->participantId = $participantId;
        $this->canSend       = $canSend;
        $this->canReceive    = $canReceive;
    }

    /** Returns the Peppol participant identifier, when present in the response. */
    public function participantId(): ?Participant
    {
        return $this->participantId;
    }

    /** Whether the response reports permission to send. */
    public function canSend(): ?bool
    {
        return $this->canSend;
    }

    /** Whether the response reports permission to receive. */
    public function canReceive(): ?bool
    {
        return $this->canReceive;
    }
}
