<?php

namespace DreaM1\EfakturaConnector\Domain\Ubl\Code;

use DreaM1\EfakturaConnector\Support\Exceptions\InvalidInputException;

/** A canonical UN/ECE Recommendation 20 code used for a UBL quantity. */
final class UnitCode
{
    private string $value;

    private function __construct(string $value)
    {
        $this->value = $value;
    }

    /**
     * Creates a canonical uppercase UN/ECE code.
     *
     * @param  string                $value Uppercase UN/ECE Recommendation 20 unit code.
     * @return self
     * @throws InvalidInputException when the value is not in canonical form
     */
    public static function fromCanonical(string $value): self
    {
        if (preg_match('/^[A-Z0-9]{1,3}$/D', $value) !== 1) {
            throw new InvalidInputException('A canonical UN/ECE unit code is required.');
        }

        return new self($value);
    }

    /** Returns the canonical code. */
    public function value(): string
    {
        return $this->value;
    }
}
