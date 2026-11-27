<?php

namespace DreaM1\EfakturaConnector\Domain\Ubl\Draft;

use DreaM1\EfakturaConnector\Support\Exceptions\InvalidInputException;

/** An immutable decimal value serialized using the UBL decimal lexical form. */
final class UblDecimal
{
    /** Maximum fractional scale accepted by minor-unit conversion. */
    private const MAX_SCALE = 1000;

    /** Decimal lexical text preserved for UBL quantity or amount elements. */
    private string $value;

    private function __construct(string $value)
    {
        $this->value = $value;
    }

    /**
     * Creates a decimal from valid decimal text without rounding or changing its digits.
     * Surrounding whitespace is removed before the value is stored.
     *
     * @param  string                $value Decimal text using a dot as the optional fractional separator.
     * @return self
     * @throws InvalidInputException when the text is not a valid decimal value.
     */
    public static function fromString(string $value): self
    {
        $value = trim($value);

        if (preg_match('/\\A[+-]?(?:[0-9]+(?:\\.[0-9]*)?|\\.[0-9]+)\\z/D', $value) !== 1) {
            throw new InvalidInputException('A valid UBL decimal value is required.');
        }

        return new self($value);
    }

    /**
     * Creates a decimal amount in minor units with exactly the requested number of places.
     *
     * @param  int                   $minorUnits Amount expressed in minor units, such as cents.
     * @param  int                   $scale      Number of fractional decimal places to write.
     * @return self
     * @throws InvalidInputException when the scale is outside the supported range.
     */
    public static function fromMinorUnits(int $minorUnits, int $scale = 2): self
    {
        self::assertScale($scale);

        if ($scale === 0) {
            return self::fromString((string) $minorUnits);
        }

        $negative        = $minorUnits < 0;
        $digits          = ltrim((string) $minorUnits, '-');
        $digits          = str_pad($digits, $scale + 1, '0', STR_PAD_LEFT);
        $decimalPosition = strlen($digits) - $scale;
        $value           = ($negative ? '-' : '') . substr($digits, 0, $decimalPosition) . '.'
            . substr($digits, $decimalPosition);

        return self::fromString($value);
    }

    /**
     * Creates a decimal rounded to exactly the requested number of places using PHP number formatting.
     *
     * @param  float                 $value Finite numeric value to format.
     * @param  int                   $scale Number of fractional decimal places to write.
     * @return self
     * @throws InvalidInputException when the value is not finite or the scale is unsupported.
     */
    public static function fromFloat(float $value, int $scale): self
    {
        self::assertScale($scale);

        if (! is_finite($value)) {
            throw new InvalidInputException('The value must be finite.');
        }

        return self::fromString(number_format($value, $scale, '.', ''));
    }

    /** Returns the decimal text after surrounding whitespace has been removed. */
    public function value(): string
    {
        return $this->value;
    }

    /** Ensures generated decimals stay within the supported scale range. */
    private static function assertScale(int $scale): void
    {
        if ($scale < 0 || $scale > self::MAX_SCALE) {
            throw new InvalidInputException(sprintf('The scale must be between zero and %d.', self::MAX_SCALE));
        }
    }
}
