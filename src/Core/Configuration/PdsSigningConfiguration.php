<?php

namespace DreaM1\EfakturaConnector\Core\Configuration;

use DreaM1\EfakturaConnector\Core\Exceptions\ConfigurationException;
use DreaM1\EfakturaConnector\Core\IntegratorApi\PdsSigningSecret;
use DreaM1\EfakturaConnector\Support\ConfigurationValues;
use DreaM1\EfakturaConnector\Support\Exceptions\InvalidInputException;

/**
 * Optional PDS signing secret configured separately from the Integrator API token.
 *
 * The secret is not attached to the Integrator API client implicitly; callers pass it explicitly
 * to each SubmitPdsSubjectsRequest.
 */
final class PdsSigningConfiguration
{
    /** Full configuration path of this section, used in validation errors. */
    private const SECTION_PATH = 'integrator_api.pds';

    private ?PdsSigningSecret $signingSecret;

    /** Creates PDS signing settings with an optional secret. */
    public function __construct(?PdsSigningSecret $signingSecret = null)
    {
        $this->signingSecret = $signingSecret;
    }

    /**
     * Creates PDS signing settings from the `integrator_api.pds` configuration section.
     *
     * An absent, null or empty `signing_secret` means the secret is not configured.
     *
     * @param  array<string, mixed>   $configuration PDS signing settings.
     * @throws ConfigurationException when the section has an unsupported key or an invalid secret.
     */
    public static function fromArray(array $configuration): self
    {
        try {
            ConfigurationValues::assertAllowedKeys($configuration, ['signing_secret'], self::SECTION_PATH);
        } catch (InvalidInputException $exception) {
            throw new ConfigurationException($exception->getMessage(), $exception);
        }

        $secret = $configuration['signing_secret'] ?? null;

        if ($secret === null || $secret === '') {
            return new self();
        }

        if (! is_string($secret)) {
            throw new ConfigurationException('The ' . self::SECTION_PATH . '.signing_secret setting must be a string.');
        }

        try {
            return new self(PdsSigningSecret::fromString($secret));
        } catch (InvalidInputException $exception) {
            throw new ConfigurationException(
                'The ' . self::SECTION_PATH . '.signing_secret setting must not be blank.',
                $exception
            );
        }
    }

    /** Reports whether a PDS signing secret is configured. */
    public function hasSigningSecret(): bool
    {
        return $this->signingSecret !== null;
    }

    /** Returns the configured secret, or null when PDS signing is not configured. */
    public function signingSecret(): ?PdsSigningSecret
    {
        return $this->signingSecret;
    }

    /**
     * Returns the configured secret for a PDS submission.
     *
     * @throws ConfigurationException when no PDS signing secret is configured.
     */
    public function requireSigningSecret(): PdsSigningSecret
    {
        if ($this->signingSecret === null) {
            throw new ConfigurationException(
                'PDS signing secret is not configured; set ' . self::SECTION_PATH . '.signing_secret.'
            );
        }

        return $this->signingSecret;
    }
}
