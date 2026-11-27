<?php

namespace DreaM1\EfakturaConnector\Core\Configuration;

use DreaM1\EfakturaConnector\Core\Exceptions\ConfigurationException;
use DreaM1\EfakturaConnector\Support\ConfigurationValues;
use DreaM1\EfakturaConnector\Support\Exceptions\InvalidInputException;
use DreaM1\EfakturaConnector\Support\ScalarArrayValue;

/** Validated, named OAuth client profiles and the profile used by default service bindings. */
final class SapiClientsConfiguration
{
    /** Alias of the client profile used by default service bindings when `default_client` is not set. */
    public const DEFAULT_CLIENT_NAME = 'default';

    /** @var array<string, SapiClientConfiguration> */
    private array $clients;

    private string $defaultClientName;

    /** @param array<string, SapiClientConfiguration> $clients */
    private function __construct(array $clients, string $defaultClientName)
    {
        $this->clients           = $clients;
        $this->defaultClientName = $defaultClientName;
    }

    /**
     * Parses the top-level connector configuration's `clients` map and `default_client` value.
     *
     * Every client profile carries its own provider URL in `clients.<alias>.base_url`; a top-level `base_url`
     * is rejected so a profile can never silently talk to another environment than the one configured for it.
     *
     * @param  array<string, mixed>   $configuration Complete package configuration.
     * @throws ConfigurationException when profiles, aliases, or the default client are invalid.
     */
    public static function fromArray(array $configuration): self
    {
        if (array_key_exists('base_url', $configuration)) {
            throw new ConfigurationException(
                'Provider base_url is configured per client profile in clients.<alias>.base_url, not at the top level.'
            );
        }

        try {
            ConfigurationValues::assertAllowedKeys(
                $configuration,
                ['clients', 'default_client', 'integrator_api', 'runtime'],
                'connector'
            );
        } catch (InvalidInputException $exception) {
            throw new ConfigurationException($exception->getMessage(), $exception);
        }

        try {
            $profiles = ConfigurationValues::section($configuration, 'clients', 'clients');
        } catch (InvalidInputException $exception) {
            throw new ConfigurationException($exception->getMessage(), $exception);
        }

        if ($profiles === []) {
            throw new ConfigurationException('At least one SAPI client must be configured.');
        }

        $clients = [];

        foreach ($profiles as $name => $profile) {
            $alias = (string) $name;

            if (preg_match('/\A[A-Za-z][A-Za-z0-9._-]*\z/', $alias) !== 1) {
                throw new ConfigurationException('SAPI client names must be non-empty aliases.');
            }

            if (! is_array($profile)) {
                throw new ConfigurationException('Each SAPI client profile must be an array.');
            }

            try {
                ConfigurationValues::assertAllowedKeys(
                    $profile,
                    ['base_url', 'client_id', 'client_secret', 'client_scope'],
                    'clients.' . $alias
                );
            } catch (InvalidInputException $exception) {
                throw new ConfigurationException($exception->getMessage(), $exception);
            }

            $clients[$alias] = SapiClientConfiguration::fromArray($profile);
        }

        $defaultClientName = trim(
            ScalarArrayValue::asString($configuration, 'default_client', self::DEFAULT_CLIENT_NAME) ?? ''
        );

        if (
            preg_match('/\A[A-Za-z][A-Za-z0-9._-]*\z/', $defaultClientName) !== 1
            || ! array_key_exists($defaultClientName, $clients)
        ) {
            throw new ConfigurationException('The default SAPI client must name a configured client.');
        }

        return new self($clients, $defaultClientName);
    }

    /**
     * Returns the configured profile for an alias.
     *
     * @throws ConfigurationException when the alias is not configured.
     */
    public function client(string $name): SapiClientConfiguration
    {
        if (! array_key_exists($name, $this->clients)) {
            throw new ConfigurationException('The requested SAPI client is not configured.');
        }

        return $this->clients[$name];
    }

    /** Returns the profile selected for default service bindings. */
    public function defaultClient(): SapiClientConfiguration
    {
        return $this->client($this->defaultClientName);
    }

    /**
     * Verifies every named client profile, including its provider URL, without making a network call.
     *
     * @throws ConfigurationException when any configured profile is incomplete or invalid
     */
    public function assertReady(): void
    {
        foreach ($this->clients as $client) {
            $client->assertReady();
        }
    }

    /** Returns the configured default alias. */
    public function defaultClientName(): string
    {
        return $this->defaultClientName;
    }

    /**
     * Returns all configured aliases in configuration order.
     *
     * @return string[]
     */
    public function clientNames(): array
    {
        return array_keys($this->clients);
    }
}
