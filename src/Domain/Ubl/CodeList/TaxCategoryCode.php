<?php

namespace DreaM1\EfakturaConnector\Domain\Ubl\CodeList;

use DreaM1\EfakturaConnector\Support\Exceptions\InvalidInputException;
use DreaM1\EfakturaConnector\Support\NonBlankString;
use DreaM1\EfakturaConnector\Support\StringValue;

/** UNCL 5305 VAT category codes used by EN 16931 (cac:TaxCategory/cbc:ID). */
final class TaxCategoryCode
{
    use StringValue;

    /** UNCL 5305 VAT category code 'AE' for reverse charge. */
    public const REVERSE_CHARGE = 'AE';
    /** UNCL 5305 VAT category code 'B' for VAT transferred in Italy. */
    public const TRANSFERRED_VAT_ITALY = 'B';
    /** UNCL 5305 VAT category code 'S' for standard rated supplies. */
    public const STANDARD = 'S';
    /** UNCL 5305 VAT category code 'Z' for zero-rated supplies. */
    public const ZERO_RATED = 'Z';
    /** UNCL 5305 VAT category code 'E' for exempt supplies. */
    public const EXEMPT = 'E';
    /** UNCL 5305 VAT category code 'G' for free exports without VAT. */
    public const FREE_EXPORT = 'G';
    /** UNCL 5305 VAT category code 'K' for intra-community supplies. */
    public const INTRA_COMMUNITY_SUPPLY = 'K';
    /** UNCL 5305 VAT category code 'L' for the Canary Islands general indirect tax. */
    public const CANARY_ISLANDS_GENERAL_INDIRECT_TAX = 'L';
    /** UNCL 5305 VAT category code 'M' for tax in Ceuta and Melilla. */
    public const CEUTA_AND_MELILLA_TAX = 'M';
    /** UNCL 5305 VAT category code 'O' for supplies outside the scope of VAT. */
    public const OUT_OF_SCOPE = 'O';

    /** Whether this value matches a code listed in this class. */
    public function isKnown(): bool
    {
        return in_array($this->value, [
            self::REVERSE_CHARGE,
            self::TRANSFERRED_VAT_ITALY,
            self::STANDARD,
            self::ZERO_RATED,
            self::EXEMPT,
            self::FREE_EXPORT,
            self::INTRA_COMMUNITY_SUPPLY,
            self::CANARY_ISLANDS_GENERAL_INDIRECT_TAX,
            self::CEUTA_AND_MELILLA_TAX,
            self::OUT_OF_SCOPE,
        ], true);
    }

    protected static function normalize(string $value): string
    {
        if (! NonBlankString::isNotBlank($value)) {
            throw new InvalidInputException('Tax category code must not be empty.');
        }

        return $value;
    }
}
