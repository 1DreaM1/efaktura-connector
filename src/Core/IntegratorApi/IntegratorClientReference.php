<?php

namespace DreaM1\EfakturaConnector\Core\IntegratorApi;

use DreaM1\EfakturaConnector\Core\Configuration\OAuthClientId;

/** Provider identifier for an OAuth client managed by the Integrator API. */
final class IntegratorClientReference
{
    private OAuthClientId $clientId;

    /**
     * Creates a reference from a provider OAuth client identifier.
     *
     * @param OAuthClientId $clientId Provider-assigned OAuth client identifier.
     */
    public function __construct(OAuthClientId $clientId)
    {
        $this->clientId = OAuthClientId::fromString(trim($clientId->value()));
    }

    /** Returns the provider OAuth client identifier. */
    public function clientId(): OAuthClientId
    {
        return $this->clientId;
    }
}
