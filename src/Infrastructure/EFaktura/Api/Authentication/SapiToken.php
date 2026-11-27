<?php

namespace DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Authentication;

/**
 * @api SAPI bearer token and its locally enforced expiry. Treat the value as a secret.
 *
 * The provider only issues `Bearer` tokens, so no token type is kept; the OAuth response metadata
 * that nothing reads (granted scope, reported lifetime) is not retained or persisted.
 */
final class SapiToken
{
    private OAuthAccessToken $accessToken;
    private int $expiresAt;

    /**
     * @param OAuthAccessToken $accessToken OAuth bearer token returned by SAPI.
     * @param int              $expiresAt   Unix timestamp at which the connector stops using the token.
     */
    public function __construct(OAuthAccessToken $accessToken, int $expiresAt)
    {
        $this->accessToken = $accessToken;
        $this->expiresAt   = $expiresAt;
    }

    /** Returns the bearer token value. */
    public function accessToken(): OAuthAccessToken
    {
        return $this->accessToken;
    }

    /** Returns the Unix timestamp at which the connector stops using the token. */
    public function expiresAt(): int
    {
        return $this->expiresAt;
    }

    /** Whether the token is no longer usable at the supplied Unix timestamp. */
    public function isExpiredAt(int $timestamp): bool
    {
        return $this->expiresAt <= $timestamp;
    }
}
