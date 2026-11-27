<?php

namespace DreaM1\EfakturaConnector\Core\Configuration;

use DreaM1\EfakturaConnector\Support\Exceptions\InvalidInputException;

/** One or more OAuth scope tokens in the RFC 6749 space-separated representation. */
final class OAuthScope
{
    /** Pattern for one RFC 6749 scope token, excluding spaces and control characters. */
    private const TOKEN_PATTERN = '[\x21\x23-\x5B\x5D-\x7E]+';
    /** Pattern for one or more scope tokens separated by a single ASCII space. */
    private const PATTERN = '/\A' . self::TOKEN_PATTERN . '(?: ' . self::TOKEN_PATTERN . ')*\z/';

    private string $value;

    private function __construct(string $value)
    {
        $this->value = $value;
    }

    /**
     * Creates an OAuth scope value without restricting provider-specific scope names.
     *
     * @throws InvalidInputException when the value is not a valid space-separated scope string.
     */
    public static function fromString(string $value): self
    {
        if (preg_match(self::PATTERN, $value) !== 1) {
            throw new InvalidInputException('OAuth scope must contain valid space-separated scope tokens.');
        }

        return new self($value);
    }

    /** Returns the exact scope string for the OAuth request or a persistence boundary. */
    public function value(): string
    {
        return $this->value;
    }

    /**
     * Returns the individual scope tokens in their configured order.
     *
     * @return string[]
     */
    public function tokens(): array
    {
        return explode(' ', $this->value);
    }

    /** Whether this scope contains the supplied token. */
    public function contains(string $token): bool
    {
        return in_array($token, $this->tokens(), true);
    }
}
