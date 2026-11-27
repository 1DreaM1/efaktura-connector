<?php

namespace DreaM1\EfakturaConnector\Core\Documents;

use DreaM1\EfakturaConnector\Domain\EFaktura\Participant;

/** Sender and receiver participants of a submitted document; either is null when the provider omitted it. */
final class SentDocumentParties
{
    private ?Participant $sender;
    private ?Participant $receiver;

    /**
     * Creates the participant pair.
     *
     * @param Participant|null $sender   Sender participant.
     * @param Participant|null $receiver Receiver participant.
     */
    public function __construct(?Participant $sender = null, ?Participant $receiver = null)
    {
        $this->sender   = $sender;
        $this->receiver = $receiver;
    }

    /** Returns the sender participant, or null when unavailable. */
    public function sender(): ?Participant
    {
        return $this->sender;
    }

    /** Returns the receiver participant, or null when unavailable. */
    public function receiver(): ?Participant
    {
        return $this->receiver;
    }
}
