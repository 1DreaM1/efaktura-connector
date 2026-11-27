<?php

namespace DreaM1\EfakturaConnector\Core\Connection\Health;

use DreaM1\EfakturaConnector\Support\Timestamp;

/** Provider recommendation about when the access token should be refreshed. */
final class TokenRefreshRecommendation
{
    private ?bool $shouldRefresh;
    private ?Timestamp $refreshRecommendedAt;

    /**
     * Creates token refresh guidance; fields are null when omitted by the provider.
     *
     * @param bool|null      $shouldRefresh        Whether the token should be refreshed.
     * @param Timestamp|null $refreshRecommendedAt Recommended token refresh time.
     */
    public function __construct(?bool $shouldRefresh = null, ?Timestamp $refreshRecommendedAt = null)
    {
        $this->shouldRefresh        = $shouldRefresh;
        $this->refreshRecommendedAt = $refreshRecommendedAt;
    }

    /** Whether the provider recommends refreshing the token. */
    public function shouldRefresh(): ?bool
    {
        return $this->shouldRefresh;
    }

    /** The recommended token refresh time. */
    public function refreshRecommendedAt(): ?Timestamp
    {
        return $this->refreshRecommendedAt;
    }
}
