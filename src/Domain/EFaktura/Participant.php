<?php

namespace DreaM1\EfakturaConnector\Domain\EFaktura;

use DreaM1\EfakturaConnector\Support\Exceptions\InvalidInputException;

/** A Peppol participant identifier in its syntactic scheme:value form. */
final class Participant
{
    /** Number of digits in a Peppol participant scheme code. */
    private const SCHEME_CODE_LENGTH = 4;
    /** Minimum length of a Peppol participant identifier value. */
    private const MIN_IDENTIFIER_VALUE_LENGTH = 1;
    /** Maximum length of a Peppol participant identifier value. */
    private const MAX_IDENTIFIER_VALUE_LENGTH = 130;

    /** Pattern for a four-digit scheme and an allowed non-empty participant identifier value. */
    private const IDENTIFIER_PATTERN = '/\A[0-9]{' . self::SCHEME_CODE_LENGTH . '}:[A-Za-z0-9._~-]{'
        . self::MIN_IDENTIFIER_VALUE_LENGTH . ',' . self::MAX_IDENTIFIER_VALUE_LENGTH . '}\z/';

    private string $scheme;
    private string $value;

    private function __construct(string $scheme, string $value)
    {
        $this->scheme = $scheme;
        $this->value  = $value;
    }

    /**
     * Parses a Peppol identifier, ignoring whitespace around it.
     *
     * @param  string|null $identifier Participant identifier in `scheme:value` form.
     * @return self|null   Parsed participant, or null when the input is malformed.
     */
    public static function fromPeppolIdentifier(?string $identifier): ?self
    {
        $identifier = preg_replace('/^[\s\p{Z}]+|[\s\p{Z}]+$/u', '', (string) $identifier);

        if ($identifier === null) {
            return null;
        }

        $separator = strpos($identifier, ':');

        if ($separator === false) {
            return null;
        }

        $scheme = substr($identifier, 0, $separator);
        $value  = substr($identifier, $separator + 1);

        if (preg_match(self::IDENTIFIER_PATTERN, $scheme . ':' . $value) !== 1) {
            return null;
        }

        return new self($scheme, $value);
    }

    /**
     * Parses a Peppol identifier or throws when it is malformed.
     *
     * @param  string                $identifier Participant identifier in `scheme:value` form.
     * @return self
     * @throws InvalidInputException when the identifier is malformed
     */
    public static function parse(string $identifier): self
    {
        $participant = self::fromPeppolIdentifier($identifier);

        if ($participant === null) {
            throw new InvalidInputException(
                sprintf(
                    'Peppol participant ID must have a %d-digit scheme and a %d to %d character value.',
                    self::SCHEME_CODE_LENGTH,
                    self::MIN_IDENTIFIER_VALUE_LENGTH,
                    self::MAX_IDENTIFIER_VALUE_LENGTH
                )
            );
        }

        return $participant;
    }

    /** Returns the four-digit participant identifier scheme. */
    public function scheme(): string
    {
        return $this->scheme;
    }

    /** Returns the participant identifier value without its scheme prefix. */
    public function value(): string
    {
        return $this->value;
    }

    /**
     * Whether both participant identifiers are equal.
     *
     * Schemes are case-sensitive; identifier values are case-insensitive.
     *
     * @param  self $other Participant identifier to compare.
     * @return bool
     */
    public function equals(self $other): bool
    {
        return $this->scheme === $other->scheme && strcasecmp($this->value, $other->value) === 0;
    }

    /** Returns the scheme and value joined as scheme:value. */
    public function identifier(): string
    {
        return $this->scheme . ':' . $this->value;
    }
}
