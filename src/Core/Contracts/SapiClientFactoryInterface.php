<?php

namespace DreaM1\EfakturaConnector\Core\Contracts;

use DreaM1\EfakturaConnector\Core\Configuration\SapiClientConfigurationInterface;
use DreaM1\EfakturaConnector\Core\Exceptions\ConfigurationException;

/** Creates an application-facing SAPI client for one configured profile. */
interface SapiClientFactoryInterface
{
    /**
     * Builds a client with the supplied alias and provider configuration.
     *
     * @throws ConfigurationException when the configured executor factory returns an invalid executor.
     */
    public function create(string $name, SapiClientConfigurationInterface $configuration): SapiClientInterface;
}
