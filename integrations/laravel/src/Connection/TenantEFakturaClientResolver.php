<?php

namespace DreaM1\EfakturaConnector\Laravel\Connection;

use DreaM1\EfakturaConnector\Core\Configuration\SapiClientConfiguration;
use DreaM1\EfakturaConnector\Core\Configuration\SapiClientConfigurationInterface;
use DreaM1\EfakturaConnector\Core\Contracts\SapiClientFactoryInterface;
use DreaM1\EfakturaConnector\Core\Contracts\SapiClientInterface;
use DreaM1\EfakturaConnector\Laravel\Contracts\TenantEFakturaCredentialsInterface;
use DreaM1\EfakturaConnector\Support\Exceptions\InvalidInputException;
use DreaM1\EfakturaConnector\Support\HashAlgorithm;

/** Creates an isolated SAPI client from OAuth credentials exposed by a tenant model. */
final class TenantEFakturaClientResolver
{
    private SapiClientFactoryInterface $factory;
    private string $configuredBaseUrl;

    /**
     * Creates a resolver that combines tenant credentials with the provider URL they all share.
     *
     * @param SapiClientFactoryInterface $factory           Factory used to create an isolated tenant client.
     * @param string                     $configuredBaseUrl Provider URL used by every tenant client.
     */
    public function __construct(SapiClientFactoryInterface $factory, string $configuredBaseUrl)
    {
        $this->factory           = $factory;
        $this->configuredBaseUrl = $configuredBaseUrl;
    }

    /**
     * Builds a client using the supplied tenant model's OAuth credentials.
     *
     * The model must already have been selected and authorized by the host application. This
     * resolver does not query tenants or make authorization decisions. It does not retain model
     * credentials or client instances between calls; persistent token caching is handled by the
     * configured SAPI token cache and is scoped by the connector configuration.
     *
     * @param  TenantEFakturaCredentialsInterface $tenant Tenant model exposing this client's OAuth credentials.
     * @return SapiClientInterface                Isolated SAPI client configured for the tenant.
     * @throws InvalidInputException              when the model does not provide a stable client key
     */

    public function forTenant(TenantEFakturaCredentialsInterface $tenant): SapiClientInterface
    {
        $clientKey = trim($tenant->efakturaClientKey());

        if ($clientKey === '') {
            throw new InvalidInputException('A tenant SAPI client key must not be empty.');
        }

        $configuration = new SapiClientConfiguration(
            $this->configuredBaseUrl,
            $tenant->efakturaClientId(),
            $tenant->efakturaClientSecret(),
            $tenant->efakturaClientScope()
        );

        // Fail before returning a client if the URL, credentials or scope are incomplete or malformed.
        $configuration->assertReady();

        // Keep database identifiers out of the public client name while making it stable per tenant.
        $clientName = 'tenant-' . hash(HashAlgorithm::SHA_256, $clientKey);

        return $this->factory->create($clientName, $configuration);
    }
}
