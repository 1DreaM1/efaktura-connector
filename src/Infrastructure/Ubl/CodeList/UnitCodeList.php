<?php

namespace DreaM1\EfakturaConnector\Infrastructure\Ubl\CodeList;

use DreaM1\EfakturaConnector\Domain\Ubl\Code\UnitCode;
use DreaM1\EfakturaConnector\Support\Exceptions\InvalidInputException;

/**
 * UN/ECE Recommendation 20 unit codes (@unitCode), read from the connector's code-list resources.
 *
 * resolve() and isCanonical() accept only exact canonical codes and back validation. fromLabel() and unit() are an
 * opt-in convenience that additionally understands common local abbreviations such as `ks`, `kg` or `hod`.
 */
final class UnitCodeList
{
    /** JSON resource key containing canonical UN/ECE unit codes. */
    private const RESOURCE_FIELD_UNITS = 'units';

    /** JSON resource key containing the unofficial unit aliases. */
    private const RESOURCE_FIELD_ALIASES = 'aliases';

    /** Characters replaced while normalizing a label; diacritics are folded so `deň` equals `den`. */
    private const LABEL_TRANSLATION = [
        'á' => 'a', 'ä' => 'a', 'č' => 'c', 'ď' => 'd', 'é' => 'e', 'ě' => 'e', 'í' => 'i', 'ĺ' => 'l', 'ľ' => 'l',
        'ň' => 'n', 'ó' => 'o', 'ô' => 'o', 'ö' => 'o', 'ŕ' => 'r', 'ř' => 'r', 'š' => 's', 'ť' => 't', 'ú' => 'u',
        'ů' => 'u', 'ü' => 'u', 'ý' => 'y', 'ž' => 'z', '²' => '2', '³' => '3',
    ];

    /** @var array<string, mixed>|null */
    private static ?array $resource = null;

    /** @var array<string, string>|null */
    private static ?array $aliases = null;

    /**
     * Whether the supplied value exactly matches a canonical code in the bundled UN/ECE list.
     *
     * @param  string $code Candidate unit code.
     * @return bool   Whether the exact code is present.
     */
    public static function isCanonical(string $code): bool
    {
        return isset(self::units()[$code]);
    }

    /**
     * Returns all canonical UN/ECE Recommendation 20/21 codes.
     *
     * @return string[]
     */
    public static function all(): array
    {
        return array_map('strval', array_keys(self::units()));
    }

    /**
     * Returns the typed value for an exact canonical UN/ECE code.
     *
     * @param  string        $unit Candidate unit code.
     * @return UnitCode|null Canonical value object when the exact code is recognized.
     */
    public static function resolve(string $unit): ?UnitCode
    {
        return self::isCanonical($unit) ? UnitCode::fromCanonical($unit) : null;
    }

    /**
     * Resolves a canonical UN/ECE code or a known unit alias such as `ks`, `kg` or `hod`.
     *
     * An exact canonical code is returned as is. Any other input is normalized (trimmed, lower-cased, diacritics
     * folded, trailing dot ignored) and looked up in the bundled aliases and then in $extraAliases, which take
     * precedence. The aliases are an unofficial convenience; canonical codes themselves are never matched
     * case-insensitively.
     *
     * @param  string                $input        Unit code or local abbreviation.
     * @param  array<string,string>  $extraAliases Additional or overriding aliases mapped to canonical codes.
     * @return UnitCode|null         Typed unit, or null when the input is neither a code nor a known alias.
     * @throws InvalidInputException when an extra alias is empty, ambiguous or targets a non-canonical code
     */
    public static function fromLabel(string $input, array $extraAliases = []): ?UnitCode
    {
        if (self::isCanonical($input)) {
            return UnitCode::fromCanonical($input);
        }

        $label = self::normalizeLabel($input);
        $extra = self::normalizeAliases($extraAliases);

        // Validate the extra aliases even for an empty label so a configuration mistake is never hidden.
        if ($label === '') {
            return null;
        }

        $code = $extra[$label] ?? self::aliases()[$label] ?? null;

        return $code === null ? null : UnitCode::fromCanonical($code);
    }

    /**
     * Like fromLabel(), but rejects an unknown unit so it can be passed straight into a draft line.
     *
     * @param  string                $input        Unit code or local abbreviation.
     * @param  array<string,string>  $extraAliases Additional or overriding aliases mapped to canonical codes.
     * @return UnitCode
     * @throws InvalidInputException when the input is neither a canonical code nor a known alias
     */
    public static function unit(string $input, array $extraAliases = []): UnitCode
    {
        $unit = self::fromLabel($input, $extraAliases);

        if ($unit === null) {
            throw new InvalidInputException(sprintf(
                'Unknown unit "%s": use a UN/ECE unit code or a known unit alias.',
                mb_substr($input, 0, 40)
            ));
        }

        return $unit;
    }

    /**
     * English name of an exact canonical UN/ECE code, or null for an unknown input.
     *
     * @param  string      $code Candidate unit code.
     * @return string|null
     */
    public static function name(string $code): ?string
    {
        return self::units()[$code] ?? null;
    }

    /** @return array<string, string> */
    private static function units(): array
    {
        $resource = self::resource();

        return is_array($resource[self::RESOURCE_FIELD_UNITS] ?? null)
            ? $resource[self::RESOURCE_FIELD_UNITS]
            : [];
    }

    /**
     * Folds a label to the form used by the alias table.
     *
     * @param string $label Unit label as entered by a user.
     */
    private static function normalizeLabel(string $label): string
    {
        $label = strtr(mb_strtolower(trim($label), 'UTF-8'), self::LABEL_TRANSLATION);
        $label = rtrim($label, '.');

        return trim((string) preg_replace('/\s+/u', ' ', $label));
    }

    /**
     * Normalizes host-supplied aliases and checks that each targets a canonical code.
     *
     * @param  array<array-key, mixed> $aliases Aliases supplied by the caller.
     * @return array<string, string>
     * @throws InvalidInputException   when an alias is empty, ambiguous after normalization or has a bad target
     */
    private static function normalizeAliases(array $aliases): array
    {
        $normalized = [];

        foreach ($aliases as $alias => $code) {
            $key = self::normalizeLabel((string) $alias);

            if ($key === '' || ! is_string($code) || ! self::isCanonical($code)) {
                throw new InvalidInputException('A unit alias must map a non-empty label to a canonical UN/ECE unit code.');
            }

            if (isset($normalized[$key]) && $normalized[$key] !== $code) {
                throw new InvalidInputException('A unit alias maps to different codes after normalization.');
            }

            $normalized[$key] = $code;
        }

        return $normalized;
    }

    /** @return array<string, string> */
    private static function aliases(): array
    {
        if (self::$aliases === null) {
            $resource = CodeListResources::readRequiredJsonArray(CodeListResources::UNIT_ALIASES_FILE);
            $aliases  = is_array($resource[self::RESOURCE_FIELD_ALIASES] ?? null) ? $resource[self::RESOURCE_FIELD_ALIASES] : [];

            self::$aliases = [];

            foreach ($aliases as $alias => $code) {
                // Ignore anything that does not point at a canonical code, so a damaged file cannot yield a bad unit.
                if (is_string($code) && self::isCanonical($code)) {
                    self::$aliases[(string) $alias] = $code;
                }
            }
        }

        return self::$aliases;
    }

    /** @return array<string, mixed> */
    private static function resource(): array
    {
        return self::$resource ??= CodeListResources::readRequiredJsonArray(CodeListResources::UNECE_REC20_NAMES_FILE);
    }
}
