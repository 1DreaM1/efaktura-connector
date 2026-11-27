<?php

namespace DreaM1\EfakturaConnector\Domain\Ubl\Draft;

use DreaM1\EfakturaConnector\Support\Exceptions\InvalidInputException;

/** An immutable Base64-encoded document attachment. */
final class Base64DocumentContent
{
    /** Validated Base64 text preserved for an embedded UBL attachment. */
    private string $value;

    private function __construct(string $value)
    {
        $this->value = $value;
    }

    /**
     * Creates content from a valid Base64 string, allowing XML whitespace between encoded characters.
     * The original string is preserved for serialization.
     *
     * @param  string                $value Base64-encoded document content.
     * @return self
     * @throws InvalidInputException when the content is not valid padded Base64.
     */
    public static function fromString(string $value): self
    {
        $compactValue = str_replace([" ", "\t", "\r", "\n"], '', $value);

        if ($compactValue === '') {
            throw new InvalidInputException('Embedded document content must not be empty.');
        }

        $decodedValue = base64_decode($compactValue, true);

        if ($decodedValue === false || base64_encode($decodedValue) !== $compactValue) {
            throw new InvalidInputException('Embedded document content must be valid Base64.');
        }

        return new self($value);
    }

    /** Returns the original Base64 text, including any XML whitespace. */
    public function value(): string
    {
        return $this->value;
    }
}
