<?php

namespace DreaM1\EfakturaConnector\Infrastructure\Ubl\CodeList;

use DreaM1\EfakturaConnector\Infrastructure\ResourcePath;
use DreaM1\EfakturaConnector\Support\Exceptions\InvariantViolationException;
use DreaM1\EfakturaConnector\Support\JsonArrayDecoder;

/** Resolves and reads bundled code-list resources. */
final class CodeListResources
{
    /** Bundled names for UN/ECE Recommendation 20 unit codes. */
    public const UNECE_REC20_NAMES_FILE = 'unece-rec20-names.json';
    /** Revision recorded in the bundled Peppol Recommendation 20 code-list metadata. */
    public const UNECE_REC20_VERSION = 'UN/ECE Revision 11e; Recommendation 21 codes use the Peppol X prefix';
    /** Peppol Billing release used for the bundled Recommendation 20/21 entries. */
    public const UNECE_REC20_RELEASE = '2026-Q4-MR';
    /** Unofficial aliases (local abbreviations) for UN/ECE unit codes; not part of the Peppol code list. */
    public const UNIT_ALIASES_FILE = 'unit-aliases.json';
    /** ISO 4217 alpha-3 currencies accepted by the bundled Peppol Billing code list. */
    public const ISO_4217_CURRENCIES_FILE = 'iso-4217-currencies.json';
    /** Version recorded in the bundled ISO 4217 code-list metadata. */
    public const ISO_4217_CURRENCIES_VERSION = '2018-01-01';
    /** Peppol code-list release used for the bundled ISO 4217 entries. */
    public const ISO_4217_CURRENCIES_RELEASE = '2026-Q4-MR';
    /** Bundled CEF Electronic Address Scheme codes. */
    public const ELECTRONIC_ADDRESS_SCHEMES_FILE = 'electronic-address-schemes.json';
    /** Peppol Billing release used for the bundled EAS entries. */
    public const ELECTRONIC_ADDRESS_SCHEMES_RELEASE = '2026-Q4-MR';
    /** Complete Peppol BIS Billing code lists that do not have individual resource files. */
    public const PEPPOL_BILLING_CODE_LISTS_FILE = 'peppol-billing-3.0-2026-Q4-MR.json';
    /** Peppol BIS Billing release used by the bundled supplementary code lists. */
    public const PEPPOL_BILLING_CODE_LISTS_RELEASE = '2026-Q4-MR';

    /**
     * Returns the directory containing bundled code-list resources.
     *
     * @return string
     */
    public static function directory(): string
    {
        return ResourcePath::packageDirectory('resources/code-lists');
    }

    /**
     * Resolves a relative path inside the bundled code-list resources.
     *
     * @param  string $relativePath Path relative to the configured resource directory.
     * @return string
     */
    public static function path(string $relativePath): string
    {
        return ResourcePath::join(self::directory(), $relativePath);
    }

    /**
     * Reads a JSON resource inside the code-list directory, returning an empty array if it is unavailable.
     *
     * @param  string                  $relativePath Path relative to the configured resource directory.
     * @return array<array-key, mixed>
     */
    public static function readJsonArray(string $relativePath): array
    {
        $path = self::readablePath($relativePath);

        if ($path === null) {
            return [];
        }

        $contents = @file_get_contents($path);

        return JsonArrayDecoder::decodeArray(is_string($contents) ? $contents : '') ?? [];
    }

    /**
     * Reads a bundled JSON resource that the code lists cannot work without.
     *
     * A missing, unreadable or malformed file means a broken installation. Returning an empty list instead would
     * make every currency, unit or scheme look unknown, so the caller gets an exception that names the file and
     * nothing is cached: a later call reads the resource again.
     *
     * @param  string                      $relativePath Path relative to the configured resource directory.
     * @return array<array-key, mixed>
     * @throws InvariantViolationException when the resource is missing, unreadable or not valid JSON.
     */
    public static function readRequiredJsonArray(string $relativePath): array
    {
        $path = self::readablePath($relativePath);

        if ($path === null) {
            throw new InvariantViolationException(sprintf(
                'Bundled code-list resource "%s" is missing or unreadable; reinstall the package.',
                $relativePath
            ));
        }

        $contents = @file_get_contents($path);
        $data     = is_string($contents) ? JsonArrayDecoder::decodeArray($contents) : null;

        if ($data === null) {
            throw new InvariantViolationException(sprintf(
                'Bundled code-list resource "%s" is not valid JSON; reinstall the package.',
                $relativePath
            ));
        }

        return $data;
    }

    /** Returns the real path of a readable file inside the code-list directory, or null. */
    private static function readablePath(string $relativePath): ?string
    {
        $directory = realpath(self::directory());
        $path      = realpath(self::path($relativePath));

        if ($directory === false || $path === false) {
            return null;
        }

        $directoryPrefix = rtrim($directory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

        $isInsideDirectory = DIRECTORY_SEPARATOR === '\\'
            ? strncasecmp($path, $directoryPrefix, strlen($directoryPrefix)) === 0
            : strpos($path, $directoryPrefix) === 0;

        if (! $isInsideDirectory || ! is_file($path) || ! is_readable($path)) {
            return null;
        }

        return $path;
    }
}
