<?php

namespace DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Authentication;

use DreaM1\EfakturaConnector\Core\Configuration\OAuthClientId;
use DreaM1\EfakturaConnector\Core\Configuration\OAuthClientSecret;
use DreaM1\EfakturaConnector\Core\Configuration\OAuthScope;

/**
 * Input for SAPI's OAuth client-credentials token request.
 *
 * It carries integration credentials and an optional requested scope; participant identity is not
 * part of OAuth credentials and is supplied on participant-scoped API operations instead.
 */
final class OAuthTokenRequest
{
    /** OAuth grant type used by SAPI's client-credentials flow. */
    public const GRANT_TYPE_CLIENT_CREDENTIALS = 'client_credentials';

    private OAuthClientId $clientId;
    private OAuthClientSecret $clientSecret;
    private ?OAuthScope $scope;

    /**
     * Creates client-credentials input for the SAPI OAuth token request.
     *
     * @param OAuthClientId     $clientId     SAPI OAuth client identifier.
     * @param OAuthClientSecret $clientSecret SAPI OAuth client secret.
     * @param OAuthScope|null   $scope        OAuth scopes requested for the token.
     */
    public function __construct(OAuthClientId $clientId, OAuthClientSecret $clientSecret, ?OAuthScope $scope = null)
    {
        $this->clientId     = $clientId;
        $this->clientSecret = $clientSecret;
        $this->scope        = $scope;
    }

    /** Returns the configured SAPI OAuth client ID. */
    public function clientId(): OAuthClientId
    {
        return $this->clientId;
    }

    /** Returns the configured SAPI OAuth client secret. */
    public function clientSecret(): OAuthClientSecret
    {
        return $this->clientSecret;
    }

    /** Returns the requested OAuth scopes, or null to use the provider default. */
    public function scope(): ?OAuthScope
    {
        return $this->scope;
    }

    /**
     * Returns the OAuth token request fields, including scope only when supplied.
     *
     * @return array<string, string>
     */
    public function toBody(): array
    {
        $body = [
            'client_id'     => $this->clientId()->value(),
            'client_secret' => $this->clientSecret()->reveal(),
            'grant_type'    => self::GRANT_TYPE_CLIENT_CREDENTIALS,
        ];

        $scope = $this->scope();

        if ($scope !== null) {
            $body['scope'] = $scope->value();
        }

        return $body;
    }
}
