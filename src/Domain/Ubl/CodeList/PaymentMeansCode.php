<?php

namespace DreaM1\EfakturaConnector\Domain\Ubl\CodeList;

use DreaM1\EfakturaConnector\Support\Exceptions\InvalidInputException;
use DreaM1\EfakturaConnector\Support\NonBlankString;
use DreaM1\EfakturaConnector\Support\StringValue;

/** UNCL 4461 payment means codes (cbc:PaymentMeansCode). */
final class PaymentMeansCode
{
    use StringValue;

    /** Payment means code '30' for credit transfer. */
    public const CREDIT_TRANSFER = '30';

    /** Payment means code '48' for bank card. */
    public const PAYMENT_CARD = '48';

    /** Payment means code '58' for SEPA credit transfer. */
    public const SEPA_CREDIT_TRANSFER = '58';

    /** Payment means code '59' for SEPA direct debit. */
    public const SEPA_DIRECT_DEBIT = '59';

    /** Whether this value matches a code listed in this class. */
    public function isKnown(): bool
    {
        return in_array($this->value, [
            self::CREDIT_TRANSFER,
            self::PAYMENT_CARD,
            self::SEPA_CREDIT_TRANSFER,
            self::SEPA_DIRECT_DEBIT,
        ], true);
    }

    protected static function normalize(string $value): string
    {
        if (! NonBlankString::isNotBlank($value)) {
            throw new InvalidInputException('Payment means code must not be empty.');
        }

        return $value;
    }
}
