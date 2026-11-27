<?php

namespace DreaM1\EfakturaConnector\Domain\Ubl\Draft;

use DreaM1\EfakturaConnector\Support\Exceptions\InvalidInputException;

/** Syntactically valid MIME media type used for an embedded UBL attachment. */
final class MimeType
{
    /** Pattern for a MIME type/subtype using valid token characters. */
    private const PATTERN = '/\A[A-Za-z0-9!#$%&\'*+.^_`|~-]+\/[A-Za-z0-9!#$%&\'*+.^_`|~-]+\z/';

    /** Validated MIME type written to EmbeddedDocumentBinaryObject/@mimeCode. */
    private string $value;

    private function __construct(string $value)
    {
        $this->value = $value;
    }

    /**
     * Creates a MIME type, preserving its supplied casing.
     *
     * @throws InvalidInputException when the value is not a valid type/subtype representation.
     */
    public static function fromString(string $value): self
    {
        if (preg_match(self::PATTERN, $value) !== 1) {
            throw new InvalidInputException('MIME type must use a valid type/subtype representation.');
        }

        return new self($value);
    }

    /** Returns the original MIME type representation. */
    public function value(): string
    {
        return $this->value;
    }
}
