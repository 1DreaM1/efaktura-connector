<?php

namespace DreaM1\EfakturaConnector\Core\IntegratorApi;

use DreaM1\EfakturaConnector\Core\Configuration\OAuthClientId;
use DreaM1\EfakturaConnector\Core\Configuration\OAuthClientSecret;

/** Newly rotated one-time OAuth client secret. */
final class IntegratorClientSecretRotation
{
    private ?OAuthClientId $clientId;
    private ?OAuthClientSecret $clientSecret;

    /**
     * Creates the provider result for a client secret rotation.
     *
     * @param OAuthClientId|null     $clientId     Provider OAuth client identifier.
     * @param OAuthClientSecret|null $clientSecret Newly rotated one-time secret.
     */
    public function __construct(?OAuthClientId $clientId, ?OAuthClientSecret $clientSecret)
    {
        $this->clientId     = $clientId;
        $this->clientSecret = $clientSecret;
    }

    /**
     * Redacts the new secret from var_dump() output.
     *
     * @return array{clientId: OAuthClientId|null, clientSecret: string|null}
     */
    public function __debugInfo(): array
    {
        return [
            'clientId'     => $this->clientId,
            'clientSecret' => $this->clientSecret !== null ? '[redacted]' : null,
        ];
    }

    /** Returns the provider OAuth client identifier, when available. */
    public function clientId(): ?OAuthClientId
    {
        return $this->clientId;
    }

    /** Returns the new secret; store it securely because it is returned only on rotation. */
    public function clientSecret(): ?OAuthClientSecret
    {
        return $this->clientSecret;
    }
}
