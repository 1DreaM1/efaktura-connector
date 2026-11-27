<?php

namespace DreaM1\EfakturaConnector\Core\Contracts;

use DreaM1\EfakturaConnector\Core\Exceptions\ConfigurationException;

/** Resolves isolated connector service sets by their configured alias. */
interface SapiClientRegistryInterface
{
    /**
     * Returns the connector service set for a configured alias.
     *
     * @throws ConfigurationException when the alias is unknown or a configured client cannot be built.
     */
    public function client(string $name): SapiClientInterface;

    /** Returns the connector service set selected by `default_client`. */
    public function defaultClient(): SapiClientInterface;

    /** Returns the alias selected by `default_client`. */
    public function defaultClientName(): string;

    /**
     * Returns configured aliases in configuration order.
     *
     * @return string[]
     */
    public function clientNames(): array;
}
