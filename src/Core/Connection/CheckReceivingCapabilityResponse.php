<?php

namespace DreaM1\EfakturaConnector\Core\Connection;

use DreaM1\EfakturaConnector\Core\Connection\Health\ParticipantCapability;
use DreaM1\EfakturaConnector\Domain\EFaktura\Participant;

/** Result of checking whether a participant receives a document type and process. */
final class CheckReceivingCapabilityResponse
{
    /** Participant checked by the provider. */
    public Participant $participant;
    /** Whether the participant can receive the requested document type and process. */
    public bool $receives;
    /** Discovered receiving endpoint, when the provider returned one. */
    public ?string $endpoint;
    /** Matching document capability, when supplied by the provider. */
    public ?ParticipantCapability $capability;

    /**
     * Creates the typed result of a participant receiving-capability check.
     *
     * @param Participant                $participant Participant whose identity scopes the provider operation.
     * @param bool                       $receives    Whether the participant can receive the requested document and process.
     * @param string|null                $endpoint    Provider endpoint used for the operation.
     * @param ParticipantCapability|null $capability  Capability to inspect or add to the participant profile.
     */
    public function __construct(
        Participant $participant,
        bool $receives,
        ?string $endpoint,
        ?ParticipantCapability $capability
    ) {
        $this->participant = $participant;
        $this->receives    = $receives;
        $this->endpoint    = $endpoint;
        $this->capability  = $capability;
    }
}
