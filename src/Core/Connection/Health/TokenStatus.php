<?php

namespace DreaM1\EfakturaConnector\Core\Connection\Health;

use DreaM1\EfakturaConnector\Core\Configuration\OAuthClientId;
use DreaM1\EfakturaConnector\Support\Timestamp;

/** Status and refresh guidance for the currently authenticated access token. */
final class TokenStatus
{
    private ?bool $valid;
    private ?string $tokenType;
    private ?OAuthClientId $clientId;
    private TokenLifetime $lifetime;
    private TokenRefreshRecommendation $refreshRecommendation;

    /** Restricts construction to the grouped status factory. */
    private function __construct(
        ?bool $valid,
        ?string $tokenType,
        ?OAuthClientId $clientId,
        TokenLifetime $lifetime,
        TokenRefreshRecommendation $refreshRecommendation
    ) {
        $this->valid                 = $valid;
        $this->tokenType             = $tokenType;
        $this->clientId              = $clientId;
        $this->lifetime              = $lifetime;
        $this->refreshRecommendation = $refreshRecommendation;
    }

    /**
     * Creates token status from provider-reported values grouped by lifetime and refresh guidance.
     *
     * @param  bool|null                  $valid                 Whether the token is valid.
     * @param  string|null                $tokenType             OAuth token type reported by the provider.
     * @param  OAuthClientId|null         $clientId              OAuth client identifier configured for the provider.
     * @param  TokenLifetime              $lifetime              Token issue, expiration and remaining lifetime.
     * @param  TokenRefreshRecommendation $refreshRecommendation Provider recommendation about token refresh.
     * @return self
     */
    public static function create(
        ?bool $valid,
        ?string $tokenType,
        ?OAuthClientId $clientId,
        TokenLifetime $lifetime,
        TokenRefreshRecommendation $refreshRecommendation
    ): self {
        return new self($valid, $tokenType, $clientId, $lifetime, $refreshRecommendation);
    }

    /** Whether the provider reports the current access token as valid. */
    public function valid(): ?bool
    {
        return $this->valid;
    }

    /** The type of token reported by the provider. */
    public function tokenType(): ?string
    {
        return $this->tokenType;
    }

    /** The OAuth client identifier associated with the token. */
    public function clientId(): ?OAuthClientId
    {
        return $this->clientId;
    }

    /** The token issue time. */
    public function issuedAt(): ?Timestamp
    {
        return $this->lifetime->issuedAt();
    }

    /** The token expiration time. */
    public function expiresAt(): ?Timestamp
    {
        return $this->lifetime->expiresAt();
    }

    /** The remaining token lifetime in seconds. */
    public function expiresInSeconds(): ?int
    {
        return $this->lifetime->expiresInSeconds();
    }

    /** Whether the provider recommends refreshing the token. */
    public function shouldRefresh(): ?bool
    {
        return $this->refreshRecommendation->shouldRefresh();
    }

    /** The recommended token refresh time. */
    public function refreshRecommendedAt(): ?Timestamp
    {
        return $this->refreshRecommendation->refreshRecommendedAt();
    }

    /** Token issue, expiration and remaining lifetime. */
    public function lifetime(): TokenLifetime
    {
        return $this->lifetime;
    }

    /** Provider recommendation about when the access token should be refreshed. */
    public function refreshRecommendation(): TokenRefreshRecommendation
    {
        return $this->refreshRecommendation;
    }
}
