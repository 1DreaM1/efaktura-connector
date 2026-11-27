<?php

namespace DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Authentication;

use DreaM1\EfakturaConnector\Support\Exceptions\InvalidInputException;
use DreaM1\EfakturaConnector\Support\RefusesSerialization;

/** @api Sensitive OAuth bearer token value. */
final class OAuthAccessToken
{
    use RefusesSerialization;

    /** RFC 6750 bearer-token character set accepted for an access token. */
    private const PATTERN = '/\A[A-Za-z0-9\-._~+\/]+=*\z/';

    private string $value;

    private function __construct(string $value)
    {
        if (preg_match(self::PATTERN, $value) !== 1) {
            throw new InvalidInputException('OAuth access token contains invalid bearer-token characters.');
        }

        $this->value = $value;
    }

    /**
     * Redacts the token from var_dump() output.
     *
     * @return array{value: string} Debug representation containing the redaction marker.
     */
    public function __debugInfo(): array
    {
        return ['value' => '[redacted]'];
    }

    /**
     * Creates an access token from its provider or cache representation.
     *
     * @throws InvalidInputException when the value contains characters outside the bearer-token syntax.
     */
    public static function fromString(string $value): self
    {
        return new self($value);
    }

    /** Returns the token for an explicit authentication or encrypted-cache boundary. */
    public function reveal(): string
    {
        return $this->value;
    }
}
