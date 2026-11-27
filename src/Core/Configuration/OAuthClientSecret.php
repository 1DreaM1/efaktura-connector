<?php

namespace DreaM1\EfakturaConnector\Core\Configuration;

use DreaM1\EfakturaConnector\Support\Exceptions\InvalidInputException;
use DreaM1\EfakturaConnector\Support\NonBlankString;
use DreaM1\EfakturaConnector\Support\RefusesSerialization;

/** Sensitive OAuth client secret; use reveal() only when constructing the provider request. */
final class OAuthClientSecret
{
    use RefusesSerialization;

    private string $value;

    private function __construct(string $value)
    {
        NonBlankString::assertNotBlank($value, 'OAuth client secret must not be blank.');

        $this->value = $value;
    }

    /**
     * Redacts the secret from var_dump() output.
     *
     * @return array{value: string} Debug representation containing the redaction marker.
     */
    public function __debugInfo(): array
    {
        return ['value' => '[redacted]'];
    }

    /**
     * Creates a secret from its configured representation.
     *
     * @throws InvalidInputException when the secret is blank.
     */
    public static function fromString(string $value): self
    {
        return new self($value);
    }

    /** Returns the secret for explicit use in OAuth authentication. */
    public function reveal(): string
    {
        return $this->value;
    }
}
