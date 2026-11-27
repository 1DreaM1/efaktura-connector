<?php

namespace DreaM1\EfakturaConnector\Core\Connection\Health;

use DreaM1\EfakturaConnector\Support\Timestamp;

/** Issue, expiry and remaining lifetime values reported for an access token. */
final class TokenLifetime
{
    private ?Timestamp $issuedAt;
    private ?Timestamp $expiresAt;
    private ?int $expiresInSeconds;

    /**
     * Creates token lifetime details; fields are null when omitted by the provider.
     *
     * @param Timestamp|null $issuedAt         Token issue timestamp.
     * @param Timestamp|null $expiresAt        Token expiration timestamp.
     * @param int|null       $expiresInSeconds Remaining token lifetime in seconds.
     */
    public function __construct(
        ?Timestamp $issuedAt = null,
        ?Timestamp $expiresAt = null,
        ?int $expiresInSeconds = null
    ) {
        $this->issuedAt         = $issuedAt;
        $this->expiresAt        = $expiresAt;
        $this->expiresInSeconds = $expiresInSeconds;
    }

    /** The token issue time. */
    public function issuedAt(): ?Timestamp
    {
        return $this->issuedAt;
    }

    /** The token expiration time. */
    public function expiresAt(): ?Timestamp
    {
        return $this->expiresAt;
    }

    /** The remaining token lifetime in seconds. */
    public function expiresInSeconds(): ?int
    {
        return $this->expiresInSeconds;
    }
}
