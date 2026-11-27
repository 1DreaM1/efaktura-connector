<?php

namespace DreaM1\EfakturaConnector\Core\Connection\Health;

use DreaM1\EfakturaConnector\Domain\EFaktura\Participant;

/** Provider profile and advertised capabilities for one Peppol participant. */
final class ParticipantProfile
{
    private Participant $participant;
    private bool $reachable;
    private ?string $name;
    private ParticipantCapabilities $capabilities;

    /**
     * @param Participant             $participant  Participant whose identity scopes the provider operation.
     * @param bool                    $reachable    Whether the participant is reachable according to the provider.
     * @param string|null             $name         Optional display name reported for the participant.
     * @param ParticipantCapabilities $capabilities Participant capabilities reported by the provider.
     */
    public function __construct(
        Participant $participant,
        bool $reachable,
        ?string $name,
        ParticipantCapabilities $capabilities
    ) {
        $this->participant  = $participant;
        $this->reachable    = $reachable;
        $this->name         = $name;
        $this->capabilities = $capabilities;
    }

    /**
     * Returns the participant described by this profile.
     *
     * @return Participant
     */
    public function participant(): Participant
    {
        return $this->participant;
    }

    /**
     * Whether the provider reports that this participant is reachable on Peppol.
     *
     * @return bool
     */
    public function reachable(): bool
    {
        return $this->reachable;
    }

    /**
     * Returns the optional display name reported by the provider.
     *
     * @return string|null
     */
    public function name(): ?string
    {
        return $this->name;
    }

    /**
     * Returns the document types and processes advertised by the provider for this participant.
     *
     * @return ParticipantCapabilities
     */
    public function capabilities(): ParticipantCapabilities
    {
        return $this->capabilities;
    }
}
