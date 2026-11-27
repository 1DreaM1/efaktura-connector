<?php

namespace DreaM1\EfakturaConnector\Core\IntegratorApi;

use DreaM1\EfakturaConnector\Domain\EFaktura\Participant;

/** Sent and received billing document counts for one participant. */
final class ParticipantDocumentSummary
{
    private ?Participant $participantId;
    private ?int $sent;
    private ?int $received;

    /**
     * Creates the provider's sent and received counts for one participant.
     *
     * @param Participant|null $participantId Participant identifier, when included.
     * @param int|null         $sent          Number of sent documents for the month.
     * @param int|null         $received      Number of received documents for the month.
     */
    public function __construct(?Participant $participantId, ?int $sent, ?int $received)
    {
        $this->participantId = $participantId;
        $this->sent          = $sent;
        $this->received      = $received;
    }

    /** Returns the participant identifier, when included. */
    public function participantId(): ?Participant
    {
        return $this->participantId;
    }

    /** Returns the monthly sent document count, when included. */
    public function sent(): ?int
    {
        return $this->sent;
    }

    /** Returns the monthly received document count, when included. */
    public function received(): ?int
    {
        return $this->received;
    }
}
