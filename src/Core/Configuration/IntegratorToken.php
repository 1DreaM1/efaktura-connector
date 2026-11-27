<?php

namespace DreaM1\EfakturaConnector\Core\Configuration;

use DreaM1\EfakturaConnector\Support\Exceptions\InvalidInputException;
use DreaM1\EfakturaConnector\Support\NonBlankString;
use DreaM1\EfakturaConnector\Support\RefusesSerialization;

/** Sensitive Integrator API token; reveal it only when constructing an authenticated request. */
final class IntegratorToken
{
    use RefusesSerialization;

    private string $value;

    private function __construct(string $value)
    {
        NonBlankString::assertNotBlank($value, 'Integrator API token must not be blank.');

        if (preg_match('/[\x00-\x08\x0A-\x1F\x7F]/', $value) === 1) {
            throw new InvalidInputException('Integrator API token must be a valid HTTP header value.');
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
     * Creates a token from its configured representation.
     *
     * @param  string                $value Configured API token.
     * @throws InvalidInputException when the token is blank or invalid as an HTTP header value.
     */
    public static function fromString(string $value): self
    {
        return new self($value);
    }

    /** Returns the token for explicit use in Integrator API authentication. */
    public function reveal(): string
    {
        return $this->value;
    }
}
