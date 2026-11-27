<?php

namespace DreaM1\EfakturaConnector\Core\Configuration;

use DreaM1\EfakturaConnector\Core\Exceptions\ConfigurationException;
use DreaM1\EfakturaConnector\Support\HttpsUrlValidator;

/** Validated provider base URL without a trailing slash. */
final class ProviderBaseUrl
{
    /** Pattern for ASCII control characters and whitespace forbidden in a provider URL. */
    private const CONTROL_CHARACTER_PATTERN = '/[\x00-\x20\x7F]/';

    private string $value;

    private function __construct(string $value)
    {
        $this->value = $value;
    }

    /**
     * Creates a validated provider base URL, normalized without trailing slashes.
     *
     * @throws ConfigurationException when the value is not a safe HTTPS base URL.
     */
    public static function fromString(string $baseUrl): self
    {
        return new self(self::normalize($baseUrl));
    }

    /**
     * Returns a safe provider base URL without trailing slashes.
     *
     * @param  string                 $baseUrl Configured provider URL to validate and normalize.
     * @return string                 Safe HTTPS URL without trailing slashes.
     * @throws ConfigurationException when the value is not a safe HTTPS base URL.
     */
    public static function normalize(string $baseUrl): string
    {
        $baseUrl = rtrim($baseUrl, '/');
        $parts   = parse_url($baseUrl);

        if (
            $baseUrl === ''
            || ! HttpsUrlValidator::hasSafeHttpsAuthority($parts)
            || isset($parts['query'])
            || isset($parts['fragment'])
            || preg_match(self::CONTROL_CHARACTER_PATTERN, $baseUrl) === 1
        ) {
            throw new ConfigurationException('Provider base URL must be a safe HTTPS URL.');
        }

        return $baseUrl;
    }

    /** Returns the URL value used at the HTTP boundary. */
    public function value(): string
    {
        return $this->value;
    }
}
