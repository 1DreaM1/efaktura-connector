<?php

namespace DreaM1\EfakturaConnector\Support;

use DreaM1\EfakturaConnector\Support\Exceptions\InvalidInputException;

/**
 * Standard shape of an immutable string value object: a private constructor, `fromString()`, `value()` and `equals()`.
 *
 * The using class implements `normalize()` once; it validates the raw string and returns the value to store.
 */
trait StringValue
{
    private string $value;

    private function __construct(string $value)
    {
        $this->value = $value;
    }

    /**
     * Creates the value from a raw string.
     *
     * @param  string                $value Raw string to validate and normalize.
     * @return self
     * @throws InvalidInputException when the string is not acceptable for this value type
     */
    public static function fromString(string $value): self
    {
        return new self(self::normalize($value));
    }

    /** Returns the stored string. */
    public function value(): string
    {
        return $this->value;
    }

    /** Whether both objects hold the same string. */
    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    /**
     * Validates a raw string and returns the value to store.
     *
     * @param  string                $value Raw string supplied to `fromString()`.
     * @return string
     * @throws InvalidInputException when the string is not acceptable for this value type
     */
    abstract protected static function normalize(string $value): string;
}
