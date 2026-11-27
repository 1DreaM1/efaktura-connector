<?php

namespace DreaM1\EfakturaConnector\Core\Documents;

use DreaM1\EfakturaConnector\Support\Timestamp;

/** Provider creation and last-update times of a submitted document; either is null when omitted. */
final class SentDocumentTiming
{
    private ?Timestamp $createdAt;
    private ?Timestamp $updatedAt;

    /**
     * Creates the timestamp pair.
     *
     * @param Timestamp|null $createdAt Provider's creation timestamp.
     * @param Timestamp|null $updatedAt Provider's last-update timestamp.
     */
    public function __construct(?Timestamp $createdAt = null, ?Timestamp $updatedAt = null)
    {
        $this->createdAt = $createdAt;
        $this->updatedAt = $updatedAt;
    }

    /** Returns the provider creation timestamp, or null when omitted. */
    public function createdAt(): ?Timestamp
    {
        return $this->createdAt;
    }

    /** Returns the provider's last-update timestamp, or null when unavailable. */
    public function updatedAt(): ?Timestamp
    {
        return $this->updatedAt;
    }
}
