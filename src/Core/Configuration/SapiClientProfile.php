<?php

namespace DreaM1\EfakturaConnector\Core\Configuration;

/** Non-secret metadata describing the SAPI client profile bound to a SAPI client. */
final class SapiClientProfile
{
    private string $baseUrl;
    private string $clientId;
    private string $clientScope;

    /**
     * Creates a safe profile view without credentials.
     *
     * @param string $baseUrl     Configured provider URL.
     * @param string $clientId    Configured OAuth client identifier.
     * @param string $clientScope Configured OAuth scope.
     */
    public function __construct(string $baseUrl, string $clientId, string $clientScope)
    {
        $this->baseUrl     = $baseUrl;
        $this->clientId    = $clientId;
        $this->clientScope = $clientScope;
    }

    /**
     * Reads the configured identity of a SAPI client without requiring complete or valid credentials.
     *
     * The profile is the single description of which provider account a client speaks for. Transport
     * scoping, shared circuit-breaker state and client metadata all derive from it.
     *
     * @param SapiClientConfigurationInterface $configuration Client settings to describe.
     */
    public static function fromConfiguration(SapiClientConfigurationInterface $configuration): self
    {
        return new self(
            $configuration->configuredBaseUrl(),
            $configuration->configuredClientId(),
            $configuration->configuredClientScope()
        );
    }

    /** Returns the configured provider URL without requiring a complete connection. */
    public function baseUrl(): string
    {
        return $this->baseUrl;
    }

    /** Returns the configured OAuth client identifier. */
    public function clientId(): string
    {
        return $this->clientId;
    }

    /** Returns the configured OAuth scope. */
    public function clientScope(): string
    {
        return $this->clientScope;
    }
}
