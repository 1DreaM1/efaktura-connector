<?php

namespace DreaM1\EfakturaConnector\Core\IntegratorApi;

use DreaM1\EfakturaConnector\Support\Timestamp;

/** Source and provider times of an Integrator participant; each is null when the provider omitted it. */
final class IntegratorParticipantTimestamps
{
    private ?Timestamp $sourceCreatedAt;
    private ?Timestamp $receivedAt;

    /**
     * Creates the timestamp pair.
     *
     * @param Timestamp|null $sourceCreatedAt Creation time from the source system.
     * @param Timestamp|null $receivedAt      Time when the provider received the participant.
     */
    public function __construct(?Timestamp $sourceCreatedAt = null, ?Timestamp $receivedAt = null)
    {
        $this->sourceCreatedAt = $sourceCreatedAt;
        $this->receivedAt      = $receivedAt;
    }

    /** Returns the source system creation time, when supplied by the provider. */
    public function sourceCreatedAt(): ?Timestamp
    {
        return $this->sourceCreatedAt;
    }

    /** Returns when the provider received the participant, when supplied. */
    public function receivedAt(): ?Timestamp
    {
        return $this->receivedAt;
    }
}
