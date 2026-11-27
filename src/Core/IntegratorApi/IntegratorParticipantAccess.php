<?php

namespace DreaM1\EfakturaConnector\Core\IntegratorApi;

use DreaM1\EfakturaConnector\Domain\EFaktura\Participant;

/** Send and receive permissions granted to one Peppol participant for an OAuth client. */
final class IntegratorParticipantAccess
{
    private Participant $participantId;
    private bool $canSend;
    private bool $canReceive;

    /**
     * Creates the permission set for one participant.
     *
     * @param Participant $participantId Peppol participant identifier.
     * @param bool        $canSend       Whether the client may send documents for this participant.
     * @param bool        $canReceive    Whether the client may receive documents for this participant.
     */
    public function __construct(Participant $participantId, bool $canSend, bool $canReceive)
    {
        $this->participantId = $participantId;
        $this->canSend       = $canSend;
        $this->canReceive    = $canReceive;
    }

    /** Returns the Peppol participant identifier. */
    public function participantId(): Participant
    {
        return $this->participantId;
    }

    /** Whether the client may send documents for this participant. */
    public function canSend(): bool
    {
        return $this->canSend;
    }

    /** Whether the client may receive documents for this participant. */
    public function canReceive(): bool
    {
        return $this->canReceive;
    }
}
