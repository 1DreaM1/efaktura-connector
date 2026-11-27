<?php

namespace DreaM1\EfakturaConnector\Domain\Ubl\Code;

use DreaM1\EfakturaConnector\Support\Exceptions\InvalidInputException;

/** A canonical Peppol country code used in UBL postal addresses. */
final class CountryCode
{
    /** Peppol's special canonical country code for Kosovo. */
    private const PEPPOL_KOSOVO = '1A';

    private string $value;

    private function __construct(string $value)
    {
        $this->value = $value;
    }

    /**
     * Creates a canonical alpha-2 code or Peppol's special Kosovo code.
     *
     * @param  string                $value Uppercase ISO 3166-1 alpha-2 code or `1A`.
     * @return self
     * @throws InvalidInputException when the value is not in canonical form
     */
    public static function fromCanonical(string $value): self
    {
        if ($value !== self::PEPPOL_KOSOVO && preg_match('/^[A-Z]{2}$/D', $value) !== 1) {
            throw new InvalidInputException('A canonical Peppol country code is required.');
        }

        return new self($value);
    }

    /** Returns the canonical code. */
    public function value(): string
    {
        return $this->value;
    }
}
