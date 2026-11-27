<?php

namespace DreaM1\EfakturaConnector\Core\Configuration;

use DreaM1\EfakturaConnector\Core\Exceptions\ConfigurationException;
use DreaM1\EfakturaConnector\Support\ConfigurationValues;
use DreaM1\EfakturaConnector\Support\Exceptions\InvalidInputException;
use DreaM1\EfakturaConnector\Support\NonBlankString;
use DreaM1\EfakturaConnector\Support\ScalarArrayValue;

/**
 * Provider URL and OAuth credentials for a SAPI client. Named client profiles share the provider URL;
 * the scope limits provider permissions and does not identify an application user or participant.
 * Participant identity is supplied per operation.
 *
 * Missing credentials or scope do not prevent application boot; assertReady() checks them without
 * contacting the provider.
 */
final class SapiClientConfiguration implements SapiClientConfigurationInterface
{
    /** OAuth scope required to send documents through SAPI. */
    public const SAPI_SCOPE_DOCUMENT_SEND = 'document:send';
    /** OAuth scope required to receive documents through SAPI. */
    public const SAPI_SCOPE_DOCUMENT_RECEIVE = 'document:receive';
    private string $baseUrl;
    private ?OAuthClientId $clientId;
    private ?OAuthClientSecret $clientSecret;
    private string $clientScope;

    /**
     * @param string $baseUrl      Provider API base URL.
     * @param string $clientId     OAuth client identifier.
     * @param string $clientSecret OAuth client secret; retained verbatim for authentication.
     * @param string $clientScope  Explicit provider-issued space-separated OAuth scopes requested from SAPI.
     */
    public function __construct(
        string $baseUrl,
        string $clientId,
        string $clientSecret,
        string $clientScope
    ) {
        $this->baseUrl = trim($baseUrl);
        $clientId      = trim($clientId);

        $this->clientId = NonBlankString::isNotBlank($clientId)
            ? OAuthClientId::fromString($clientId)
            : null;

        $this->clientSecret = NonBlankString::isNotBlank($clientSecret)
            ? OAuthClientSecret::fromString($clientSecret)
            : null;

        $this->clientScope = trim($clientScope);
    }

    /**
     * Creates connection settings for a standalone connector configuration.
     *
     * @param  array<string, mixed>   $configuration Standalone provider URL and OAuth settings.
     * @return self
     * @throws ConfigurationException when the settings contain unknown keys.
     * @throws InvalidInputException  when a configured non-empty OAuth client ID is malformed
     */
    public static function fromArray(array $configuration): self
    {
        try {
            ConfigurationValues::assertAllowedKeys(
                $configuration,
                ['base_url', 'client_id', 'client_secret', 'client_scope'],
                'connector'
            );
        } catch (InvalidInputException $exception) {
            throw new ConfigurationException($exception->getMessage(), $exception);
        }

        return new self(
            ScalarArrayValue::asString($configuration, 'base_url', '') ?? '',
            ScalarArrayValue::asString($configuration, 'client_id', '') ?? '',
            ScalarArrayValue::asString($configuration, 'client_secret', '') ?? '',
            ScalarArrayValue::asString($configuration, 'client_scope', '') ?? ''
        );
    }

    /**
     * Returns the validated provider base URL value without a trailing slash.
     *
     * @throws ConfigurationException when the configured URL is missing or invalid
     */
    public function baseUrl(): ProviderBaseUrl
    {
        return ProviderBaseUrl::fromString($this->baseUrl);
    }

    /**
     * Verifies that this configuration is ready for provider requests without making a network call.
     *
     * @throws ConfigurationException when the URL, credentials or OAuth scope are missing or invalid
     */
    public function assertReady(): void
    {
        $this->baseUrl();
        $this->clientId();
        $this->clientSecret();
        $this->clientScope();
    }

    /** Configured URL normalized for identity checks, without validating it for requests. */
    public function configuredBaseUrl(): string
    {
        return rtrim($this->baseUrl, '/');
    }

    /** Returns the configured client ID without requiring complete credentials. */
    public function configuredClientId(): string
    {
        return $this->clientId === null ? '' : $this->clientId->value();
    }

    /** Returns the configured OAuth client ID as a value object. */
    public function clientId(): OAuthClientId
    {
        $this->assertCredentials();

        return $this->clientId;
    }

    /** Returns the configured OAuth client secret as a redacted value object. */
    public function clientSecret(): OAuthClientSecret
    {
        $this->assertCredentials();

        return $this->clientSecret;
    }

    /** Returns the configured scope without validating it for a token request. */
    public function configuredClientScope(): string
    {
        return $this->clientScope;
    }

    /**
     * Returns the configured OAuth scopes as a validated value object.
     *
     * @throws ConfigurationException when no scope is configured
     */
    public function clientScope(): OAuthScope
    {
        if ($this->clientScope === '') {
            throw new ConfigurationException('OAuth scope is not configured.');
        }

        try {
            return OAuthScope::fromString($this->clientScope);
        } catch (InvalidInputException $exception) {
            throw new ConfigurationException('OAuth scope configuration is invalid.', $exception);
        }
    }

    /**
     * Rejects configurations without both OAuth client credentials.
     *
     * @phpstan-assert !null $this->clientId
     * @phpstan-assert !null $this->clientSecret
     */
    private function assertCredentials(): void
    {
        if ($this->clientId === null || $this->clientSecret === null) {
            throw new ConfigurationException('OAuth client ID or secret is not configured.');
        }
    }
}
