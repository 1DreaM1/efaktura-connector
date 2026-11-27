<?php

namespace DreaM1\EfakturaConnector\Infrastructure\Ubl\CodeList;

use DreaM1\EfakturaConnector\Domain\Ubl\Code\CurrencyCode;

/** ISO 4217 alpha-3 currency codes from the bundled Peppol Billing code list. */
final class CurrencyCodeList
{
    /** JSON resource key containing the bundled ISO 4217 currency entries. */
    private const RESOURCE_FIELD_CURRENCIES = 'currencies';

    /** @var array<string, mixed>|null */
    private static ?array $resource = null;

    /** @var array<string, string>|null */
    private static ?array $currencyMap = null;

    /**
     * Returns all bundled ISO 4217 codes in their canonical uppercase form.
     *
     * @return string[]
     */
    public static function all(): array
    {
        return array_keys(self::currencies());
    }

    /**
     * Whether the input resolves to one of the bundled ISO 4217 codes.
     *
     * @param  string|null $code Candidate currency code.
     * @return bool        Whether a bundled currency code matches.
     */
    public static function contains(?string $code): bool
    {
        return self::canonicalCode($code) !== null;
    }

    /**
     * Returns the typed canonical currency code, or null for an unknown value.
     *
     * @param  string|null       $code Candidate currency code.
     * @return CurrencyCode|null Canonical value object when the code is supported.
     */
    public static function resolve(?string $code): ?CurrencyCode
    {
        $canonicalCode = self::canonicalCode($code);

        return $canonicalCode !== null ? CurrencyCode::fromCanonical($canonicalCode) : null;
    }

    /**
     * Whether the value is already an exact canonical code in the bundled ISO 4217 list.
     *
     * @param  string|null $code Candidate currency code.
     * @return bool        Whether the input exactly matches a canonical code.
     */
    public static function isCanonical(?string $code): bool
    {
        return $code !== null && isset(self::currencies()[$code]);
    }

    /**
     * English name of a recognized ISO 4217 code, or null for an unknown input.
     *
     * @param  string      $code Code value to validate, map, or report.
     * @return string|null
     */
    public static function name(string $code): ?string
    {
        $canonicalCode = self::canonicalCode($code);

        return $canonicalCode !== null ? self::currencies()[$canonicalCode] : null;
    }

    /**
     * Returns the codes and English names suitable for a selection list.
     *
     * @return array<string, string>
     */
    public static function options(): array
    {
        $options = [];

        foreach (self::currencies() as $code => $name) {
            $options[$code] = $code . ' – ' . $name;
        }

        return $options;
    }

    private static function canonicalCode(?string $code): ?string
    {
        if ($code === null) {
            return null;
        }

        $canonicalCode = strtoupper(trim($code));

        return isset(self::currencies()[$canonicalCode]) ? $canonicalCode : null;
    }

    /** @return array<string, string> */
    private static function currencies(): array
    {
        if (self::$currencyMap === null) {
            $resource   = self::resource();
            $currencies = is_array($resource[self::RESOURCE_FIELD_CURRENCIES] ?? null)
                ? $resource[self::RESOURCE_FIELD_CURRENCIES]
                : [];

            $validCurrencies = array_filter($currencies, static function ($name, $code) {
                return is_string($code)
                    && preg_match('/^[A-Z]{3}$/D', $code) === 1
                    && is_string($name)
                    && trim($name) !== '';
            }, ARRAY_FILTER_USE_BOTH);

            self::$currencyMap = $validCurrencies;
        }

        return self::$currencyMap;
    }

    /** @return array<string, mixed> */
    private static function resource(): array
    {
        return self::$resource ??= CodeListResources::readRequiredJsonArray(CodeListResources::ISO_4217_CURRENCIES_FILE);
    }
}
