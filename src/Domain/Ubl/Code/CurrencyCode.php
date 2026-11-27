<?php

namespace DreaM1\EfakturaConnector\Domain\Ubl\Code;

use DreaM1\EfakturaConnector\Support\Exceptions\InvalidInputException;

/** A canonical ISO 4217 alpha-3 currency code used by a UBL document. */
final class CurrencyCode
{
    private string $value;

    private function __construct(string $value)
    {
        $this->value = $value;
    }

    /**
     * Creates a canonical uppercase ISO 4217 code.
     *
     * @param  string                $value Three-letter uppercase ISO 4217 code.
     * @return self
     * @throws InvalidInputException when the value is not in canonical form
     */
    public static function fromCanonical(string $value): self
    {
        if (preg_match('/^[A-Z]{3}$/D', $value) !== 1) {
            throw new InvalidInputException('A canonical ISO 4217 currency code is required.');
        }

        return new self($value);
    }

    /** Returns the canonical code. */
    public function value(): string
    {
        return $this->value;
    }
}
