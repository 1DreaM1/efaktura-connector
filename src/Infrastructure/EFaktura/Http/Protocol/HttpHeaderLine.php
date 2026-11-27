<?php

namespace DreaM1\EfakturaConnector\Infrastructure\EFaktura\Http\Protocol;

use DreaM1\EfakturaConnector\Support\Exceptions\InvalidInputException;

/** An HTTP header name and value represented as a complete header line. */
final class HttpHeaderLine
{
    /** Pattern for control characters forbidden in outbound HTTP header values. */
    private const INVALID_VALUE_PATTERN = '/[\x00-\x08\x0A-\x1F\x7F]/';

    private HttpHeaderName $name;
    private string $value;

    /** Stores a header name and value supplied by a parsing or validation factory. */
    private function __construct(HttpHeaderName $name, string $value)
    {
        $this->name  = $name;
        $this->value = $value;
    }

    /**
     * Creates and validates an outbound header line from its name and value.
     *
     * @throws InvalidInputException when the value contains forbidden control characters.
     */
    public static function fromParts(HttpHeaderName $name, string $value): self
    {
        self::assertValid($name->value(), $value);

        return new self($name, $value);
    }

    /**
     * Parses a header line, trimming surrounding whitespace from its name and value.
     * Returns null for non-string input, a missing separator, embedded CR/LF, or an invalid name or value.
     *
     * @param  mixed     $line Header line in "Name: value" form.
     * @return self|null
     */
    public static function parse($line): ?self
    {
        if (! is_string($line)) {
            return null;
        }

        if (strpos($line, "\r") !== false || strpos($line, "\n") !== false) {
            return null;
        }

        // Split only at the first colon; a valid header value may contain further colons.
        $separator = strpos($line, ':');

        if ($separator === false) {
            return null;
        }

        // Header names compare case-insensitively, while surrounding field whitespace is discarded.
        try {
            $name  = HttpHeaderName::fromString(trim(substr($line, 0, $separator)));
            $value = trim(substr($line, $separator + 1));

            return self::fromParts($name, $value);
        } catch (InvalidInputException $exception) {
            return null;
        }
    }

    /**
     * Validates one outbound header name and value before formatting the header line.
     *
     * @throws InvalidInputException when the name or value is not valid for an HTTP header.
     */
    public static function assertValid(string $name, string $value): void
    {
        HttpHeaderName::fromString($name);

        if (preg_match(self::INVALID_VALUE_PATTERN, $value) === 1) {
            throw new InvalidInputException('HTTP header value must not contain control characters.');
        }
    }

    /**
     * Returns the header name normalized to lowercase.
     *
     * @return string
     */
    public function name(): string
    {
        return $this->name->normalized();
    }

    /** Returns the typed HTTP header name. */
    public function headerName(): HttpHeaderName
    {
        return $this->name;
    }

    /** Formats the name and value as a complete HTTP header line. */
    public function toString(): string
    {
        return $this->name->value() . ': ' . $this->value;
    }

    /** Returns the stored header value; parsed values are trimmed, supplied values are preserved. */
    public function value(): string
    {
        return $this->value;
    }
}
