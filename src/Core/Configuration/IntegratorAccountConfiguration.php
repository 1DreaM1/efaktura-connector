<?php

namespace DreaM1\EfakturaConnector\Core\Configuration;

use DreaM1\EfakturaConnector\Core\Exceptions\ConfigurationException;
use DreaM1\EfakturaConnector\Core\IntegratorApi\IntegratorId;
use DreaM1\EfakturaConnector\Support\ConfigurationValues;
use DreaM1\EfakturaConnector\Support\Exceptions\InvalidInputException;
use DreaM1\EfakturaConnector\Support\ScalarArrayValue;

/** Provider URL and Integrator API credentials for one integrator account. */
final class IntegratorAccountConfiguration
{
    private ProviderBaseUrl $baseUrl;
    private IntegratorId $integratorId;
    private IntegratorToken $token;

    /**
     * Creates Integrator API configuration scoped to one provider integrator account.
     *
     * @param  string                 $baseUrl      Safe HTTPS provider URL.
     * @param  IntegratorId           $integratorId Provider-assigned integrator identifier.
     * @param  IntegratorToken        $token        Integrator API token sent in X-Integrator-Token.
     * @throws ConfigurationException when the base URL is invalid.
     */
    public function __construct(string $baseUrl, IntegratorId $integratorId, IntegratorToken $token)
    {
        $this->baseUrl      = ProviderBaseUrl::fromString($baseUrl);
        $this->integratorId = $integratorId;
        $this->token        = $token;
    }

    /**
     * Creates Integrator API settings from the `integrator_api` configuration section.
     *
     * @param  array<string, mixed>   $configuration Integrator API settings.
     * @throws ConfigurationException when the settings contain an unsupported key or invalid provider URL.
     * @throws InvalidInputException  when the integrator identifier or token is missing or invalid.
     */
    public static function fromArray(array $configuration): self
    {
        try {
            ConfigurationValues::assertAllowedKeys(
                $configuration,
                ['base_url', 'integrator_id', 'token'],
                'integrator_api'
            );
        } catch (InvalidInputException $exception) {
            throw new ConfigurationException($exception->getMessage(), $exception);
        }

        return new self(
            ScalarArrayValue::asString($configuration, 'base_url', '') ?? '',
            IntegratorId::fromString(ScalarArrayValue::asString($configuration, 'integrator_id', '') ?? ''),
            IntegratorToken::fromString(ScalarArrayValue::asString($configuration, 'token', '') ?? '')
        );
    }

    /** Returns the validated provider base URL. */
    public function baseUrl(): ProviderBaseUrl
    {
        return $this->baseUrl;
    }

    /** Returns the provider-assigned integrator identifier. */
    public function integratorId(): IntegratorId
    {
        return $this->integratorId;
    }

    /** Returns the API token for explicit authentication use. */
    public function token(): IntegratorToken
    {
        return $this->token;
    }
}
