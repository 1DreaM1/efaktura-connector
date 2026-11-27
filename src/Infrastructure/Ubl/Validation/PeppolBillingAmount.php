<?php

namespace DreaM1\EfakturaConnector\Infrastructure\Ubl\Validation;

/** Validates decimal amounts used by Peppol Billing rules. */
final class PeppolBillingAmount
{
    /** Pattern for signed decimal values with at most two fractional digits. */
    private const PATTERN = '/\A[+-]?(?:[0-9]+(?:\.[0-9]{0,2})?|\.[0-9]{1,2})\z/';

    /**
     * Whether the value is an XML Schema decimal with no more than two fraction digits.
     *
     * @param  string|null $amount Decimal value to check.
     * @return bool        Whether the value follows the supported amount format.
     */
    public static function isValid(?string $amount): bool
    {
        return $amount !== null && preg_match(self::PATTERN, $amount) === 1;
    }

    /**
     * Whether the value is a valid amount greater than zero, using decimal-string checks.
     *
     * @param  string|null $amount Decimal value to check.
     * @return bool        Whether the value is valid and positive.
     */
    public static function isPositive(?string $amount): bool
    {
        // With no minus sign, a valid decimal is positive exactly when it contains a non-zero digit.
        // Keep the check on the original string to avoid floating-point rounding.
        return $amount !== null
            && self::isValid($amount)
            && $amount[0] !== '-'
            && preg_match('/[1-9]/', $amount) === 1;
    }
}
