<?php

namespace DreaM1\EfakturaConnector\Core\Configuration;

use DreaM1\EfakturaConnector\Core\Exceptions\ConfigurationException;

/**
 * Provider URL and OAuth settings for a SAPI client. Named client profiles share the provider URL.
 *
 * `configured*()` accessors expose normalized configured values without requiring the connection
 * to be ready for a request. The other accessors validate the value they need and throw when it is
 * missing or unsafe. Participant identity is supplied to participant-scoped operations separately.
 * Intended for consumption by host applications; implementing it in a host class is not supported and new methods may be added in minor releases.
 */
interface SapiClientConfigurationInterface
{
    /**
     * Verifies that the URL, OAuth credentials and scope are complete without contacting the provider.
     *
     * @throws ConfigurationException when a required value is missing or invalid
     */
    public function assertReady(): void;

    /**
     * Returns the validated provider base URL object without a trailing slash.
     *
     * @throws ConfigurationException when the URL is missing or is not a safe HTTPS URL
     */
    public function baseUrl(): ProviderBaseUrl;

    /**
     * Returns the configured base URL without trailing slashes, for identity comparisons.
     *
     * This accessor does not validate the URL for provider requests.
     */
    public function configuredBaseUrl(): string;

    /** Returns the configured client ID without requiring a complete OAuth credential pair. */
    public function configuredClientId(): string;

    /**
     * Returns the OAuth client ID.
     *
     * @throws ConfigurationException when the client ID or client secret is missing
     */
    public function clientId(): OAuthClientId;

    /**
     * Returns the OAuth client secret as a redacted value object.
     *
     * @throws ConfigurationException when the client ID or client secret is missing
     */
    public function clientSecret(): OAuthClientSecret;

    /** Returns the configured OAuth scope without requiring complete client credentials. */
    public function configuredClientScope(): string;

    /**
     * Returns the OAuth scope value object.
     *
     * @throws ConfigurationException when the scope is empty or malformed
     */
    public function clientScope(): OAuthScope;
}
