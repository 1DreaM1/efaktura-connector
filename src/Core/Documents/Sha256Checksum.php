<?php

namespace DreaM1\EfakturaConnector\Core\Documents;

use DreaM1\EfakturaConnector\Support\Exceptions\InvalidInputException;
use DreaM1\EfakturaConnector\Support\HashAlgorithm;

/** Lowercase SHA-256 checksum of document content. */
final class Sha256Checksum
{
    /** Pattern for a lowercase 64-character hexadecimal SHA-256 digest. */
    private const PATTERN = '/\A[a-f0-9]{64}\z/';

    private string $value;

    private function __construct(string $value)
    {
        $this->value = $value;
    }

    /**
     * Creates a checksum from its lowercase hexadecimal representation.
     *
     * @throws InvalidInputException when the value is not a lowercase 64-character SHA-256 checksum.
     */
    public static function fromString(string $value): self
    {
        if (preg_match(self::PATTERN, $value) !== 1) {
            throw new InvalidInputException('Checksum must be a lowercase SHA-256 hexadecimal value.');
        }

        return new self($value);
    }

    /** Calculates the SHA-256 checksum for the exact supplied content bytes. */
    public static function fromContent(string $content): self
    {
        return new self(hash(HashAlgorithm::SHA_256, $content));
    }

    /** Returns the lowercase hexadecimal representation. */
    public function value(): string
    {
        return $this->value;
    }

    /** Checks whether this checksum matches the exact supplied content bytes. */
    public function matchesContent(string $content): bool
    {
        return hash_equals($this->value, hash(HashAlgorithm::SHA_256, $content));
    }
}
