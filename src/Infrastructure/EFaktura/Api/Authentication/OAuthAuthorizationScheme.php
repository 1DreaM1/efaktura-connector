<?php

namespace DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Authentication;

use DreaM1\EfakturaConnector\Support\Exceptions\InvalidInputException;

/** @api OAuth authorization schemes supported by the SAPI connector. */
final class OAuthAuthorizationScheme
{
    /** HTTP authorization scheme used for SAPI access tokens. */
    public const BEARER = 'Bearer';

    private string $value;

    private function __construct(string $value)
    {
        $this->value = $value;
    }

    /**
     * Creates a supported authorization scheme from the provider representation.
     *
     * @throws InvalidInputException when the scheme is not supported.
     */
    public static function fromString(string $value): self
    {
        if ($value !== self::BEARER) {
            throw new InvalidInputException('Unsupported OAuth authorization scheme.');
        }

        return new self($value);
    }

    /** Returns the scheme for HTTP authorization header serialization. */
    public function value(): string
    {
        return $this->value;
    }
}
