<?php

namespace DreaM1\EfakturaConnector\Tests\Support;

/** Fakes a Laravel cache lock for coordination adapter tests. */
final class CoordinationCacheLockFake
{
    private int $leaseSeconds;
    private int $releaseCount = 0;
    private ?int $waitSeconds = null;

    /** @param int $leaseSeconds Lease duration reported by the fake lock. */
    public function __construct(int $leaseSeconds)
    {
        $this->leaseSeconds = $leaseSeconds;
    }

    /**
     * Simulates successful acquisition without waiting.
     *
     * @param  int  $seconds Maximum wait passed to the Laravel cache lock.
     * @return bool Always true in this fake.
     */
    public function block(int $seconds): bool
    {
        $this->waitSeconds = $seconds;

        return true;
    }

    /** Records release and reports success. */
    public function release(): bool
    {
        $this->releaseCount++;

        return true;
    }

    /** Returns how many times the fake lock has been released. */
    public function releaseCount(): int
    {
        return $this->releaseCount;
    }

    /** Returns the cache lock's initial lease duration. */
    public function leaseSeconds(): int
    {
        return $this->leaseSeconds;
    }

    /** Returns the last wait duration passed to block(). */
    public function waitSeconds(): ?int
    {
        return $this->waitSeconds;
    }
}
