<?php

namespace DreaM1\EfakturaConnector\Infrastructure\Ubl\CodeList;

use DreaM1\EfakturaConnector\Domain\Ubl\Code\CountryCode;
use Locale;

/** ISO 3166-1 country codes and Peppol's special codes accepted for UBL addresses. */
final class CountryCodeList
{
    /** Peppol Billing release used for the bundled country codes. */
    public const PEPPOL_RELEASE = '2026-Q4-MR';
    /** ISO 3166-1 alpha-2 code for Germany. */
    public const GERMANY = 'DE';
    /** ISO 3166-1 alpha-2 code for Slovakia. */
    public const SLOVAKIA = 'SK';

    /** @var string[] ISO 3166-1 alpha-2 codes and Peppol's special country codes accepted in UBL addresses. */
    private const CODES = [
        '1A', 'AD', 'AE', 'AF', 'AG', 'AI', 'AL', 'AM', 'AO', 'AQ', 'AR', 'AS', 'AT', 'AU', 'AW', 'AX', 'AZ',
        'BA', 'BB', 'BD', 'BE', 'BF', 'BG', 'BH', 'BI', 'BJ', 'BL', 'BM', 'BN', 'BO', 'BQ', 'BR', 'BS', 'BT',
        'BV', 'BW', 'BY', 'BZ', 'CA', 'CC', 'CD', 'CF', 'CG', 'CH', 'CI', 'CK', 'CL', 'CM', 'CN', 'CO', 'CR',
        'CU', 'CV', 'CW', 'CX', 'CY', 'CZ', 'DE', 'DJ', 'DK', 'DM', 'DO', 'DZ', 'EC', 'EE', 'EG', 'EH', 'ER',
        'ES', 'ET', 'FI', 'FJ', 'FK', 'FM', 'FO', 'FR', 'GA', 'GB', 'GD', 'GE', 'GF', 'GG', 'GH', 'GI', 'GL',
        'GM', 'GN', 'GP', 'GQ', 'GR', 'GS', 'GT', 'GU', 'GW', 'GY', 'HK', 'HM', 'HN', 'HR', 'HT', 'HU', 'ID',
        'IE', 'IL', 'IM', 'IN', 'IO', 'IQ', 'IR', 'IS', 'IT', 'JE', 'JM', 'JO', 'JP', 'KE', 'KG', 'KH', 'KI',
        'KM', 'KN', 'KP', 'KR', 'KW', 'KY', 'KZ', 'LA', 'LB', 'LC', 'LI', 'LK', 'LR', 'LS', 'LT', 'LU', 'LV',
        'LY', 'MA', 'MC', 'MD', 'ME', 'MF', 'MG', 'MH', 'MK', 'ML', 'MM', 'MN', 'MO', 'MP', 'MQ', 'MR', 'MS',
        'MT', 'MU', 'MV', 'MW', 'MX', 'MY', 'MZ', 'NA', 'NC', 'NE', 'NF', 'NG', 'NI', 'NL', 'NO', 'NP', 'NR',
        'NU', 'NZ', 'OM', 'PA', 'PE', 'PF', 'PG', 'PH', 'PK', 'PL', 'PM', 'PN', 'PR', 'PS', 'PT', 'PW', 'PY',
        'QA', 'RE', 'RO', 'RS', 'RU', 'RW', 'SA', 'SB', 'SC', 'SD', 'SE', 'SG', 'SH', 'SI', 'SJ', 'SK', 'SL',
        'SM', 'SN', 'SO', 'SR', 'SS', 'ST', 'SV', 'SX', 'SY', 'SZ', 'TC', 'TD', 'TF', 'TG', 'TH', 'TJ', 'TK',
        'TL', 'TM', 'TN', 'TO', 'TR', 'TT', 'TV', 'TW', 'TZ', 'UA', 'UG', 'UM', 'US', 'UY', 'UZ', 'VA', 'VC',
        'VE', 'VG', 'VI', 'VN', 'VU', 'WF', 'WS', 'XI', 'YE', 'YT', 'ZA', 'ZM', 'ZW',
    ];

    /** @var array<string, string> Human-readable names for special Peppol country codes. */
    private const SPECIAL_NAMES = [
        '1A' => 'Kosovo',
        'XI' => 'Severné Írsko (Spojené kráľovstvo)',
    ];

    /**
     * Returns all supported ISO and Peppol special country codes.
     *
     * @return string[]
     */
    public static function all(): array
    {
        return self::CODES;
    }

    /**
     * Whether the supplied value resolves after case and whitespace normalization.
     *
     * @param  string|null $code Country code to check.
     * @return bool
     */
    public static function contains(?string $code): bool
    {
        return self::canonicalCode($code) !== null;
    }

    /**
     * Returns a typed country code, or null when the normalized input is unsupported.
     *
     * @param  string|null      $code Country code to resolve.
     * @return CountryCode|null
     */
    public static function resolve(?string $code): ?CountryCode
    {
        $canonicalCode = self::canonicalCode($code);

        return $canonicalCode !== null ? CountryCode::fromCanonical($canonicalCode) : null;
    }

    /**
     * Checks exact canonical spelling and membership without normalization.
     *
     * @param  string|null $code Country code to check.
     * @return bool
     */
    public static function isCanonical(?string $code): bool
    {
        return $code !== null && self::isSupportedCanonicalCode($code);
    }

    /**
     * Returns a localized Slovak display name, or the normalized code for unknown input.
     *
     * @param  string $code Country code to name.
     * @return string
     */
    public static function name(string $code): string
    {
        $code = self::normalizeCode($code);

        if (isset(self::SPECIAL_NAMES[$code])) {
            return self::SPECIAL_NAMES[$code];
        }

        if (! self::isSupportedCanonicalCode($code) || ! class_exists('Locale')) {
            return $code;
        }

        $name = Locale::getDisplayRegion('en_' . $code, 'sk');

        return is_string($name) && $name !== '' && $name !== $code ? $name : $code;
    }

    /**
     * Returns country-code options mapped to their code and localized display name.
     *
     * @return array<string, string>
     */
    public static function options(): array
    {
        $options = [];

        foreach (self::CODES as $code) {
            $options[$code] = $code . ' – ' . self::name($code);
        }

        return $options;
    }

    private static function canonicalCode(?string $code): ?string
    {
        if ($code === null) {
            return null;
        }

        $canonicalCode = self::normalizeCode($code);

        return self::isSupportedCanonicalCode($canonicalCode) ? $canonicalCode : null;
    }

    private static function normalizeCode(string $code): string
    {
        return strtoupper(trim($code));
    }

    private static function isSupportedCanonicalCode(string $code): bool
    {
        return in_array($code, self::CODES, true);
    }
}
