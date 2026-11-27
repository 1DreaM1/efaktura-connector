<?php

namespace DreaM1\EfakturaConnector\Core\Configuration;

use DreaM1\EfakturaConnector\Support\Exceptions\InvalidInputException;
use DreaM1\EfakturaConnector\Support\NonBlankString;

/** OAuth client identifier issued by the provider. */
final class OAuthClientId
{
    private string $value;

    private function __construct(string $value)
    {
        NonBlankString::assertNotBlank($value, 'OAuth client ID must not be empty.');

        $this->value = $value;
    }

    /**
     * Creates a client ID from its configured or provider-issued representation.
     *
     * @throws InvalidInputException when the identifier is blank.
     */
    public static function fromString(string $value): self
    {
        return new self($value);
    }

    /** Returns the client ID for an authentication or persistence boundary. */
    public function value(): string
    {
        return $this->value;
    }
}
