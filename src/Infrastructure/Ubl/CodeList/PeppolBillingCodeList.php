<?php

namespace DreaM1\EfakturaConnector\Infrastructure\Ubl\CodeList;

/**
 * Resolves supplementary Peppol BIS Billing code lists bundled with the package.
 *
 * The identifiers in this class name UBL Billing lists, not transport participant schemes.
 */
final class PeppolBillingCodeList
{
    /** Identifier for the Peppol Billing ICD scheme list. */
    public const ICD = 'icd';
    /** Identifier for the Peppol Billing VAT category list. */
    public const VAT_CATEGORY = 'vat_category';
    /** Identifier for the Peppol Billing item type list. */
    public const ITEM_TYPE = 'item_type';
    /** Identifier for the Peppol Billing invoice type list. */
    public const INVOICE_TYPE = 'invoice_type';
    /** Identifier for the Peppol Billing credit note type list. */
    public const CREDIT_NOTE_TYPE = 'credit_note_type';
    /** Identifier for the Peppol Billing invoiced object scheme list. */
    public const INVOICED_OBJECT_SCHEME = 'invoiced_object_scheme';
    /** Identifier for the Peppol Billing VAT date code list. */
    public const VAT_DATE_CODE = 'vat_date_code';
    /** Identifier for the Peppol Billing payment means list. */
    public const PAYMENT_MEANS = 'payment_means';
    /** Identifier for the Peppol Billing allowance reason list. */
    public const ALLOWANCE_REASON = 'allowance_reason';
    /** Identifier for the Peppol Billing charge reason list. */
    public const CHARGE_REASON = 'charge_reason';
    /** Identifier for the Peppol Billing MIME code list. */
    public const MIME = 'mime';
    /** Identifier for the Peppol Billing SEPA payment means list. */
    public const SEPA = 'sepa';
    /** Identifier for the Peppol Billing VAT exemption reason list. */
    public const VATEX = 'vatex';

    /** @var array<string, mixed>|null */
    private static ?array $resource = null;

    /** @var array<string, array<string, string>> */
    private static array $codeMaps = [];

    /**
     * Returns the identifiers of all bundled Peppol Billing lists.
     *
     * @return string[]
     */
    public static function identifiers(): array
    {
        return [
            self::ICD,
            self::VAT_CATEGORY,
            self::ITEM_TYPE,
            self::INVOICE_TYPE,
            self::CREDIT_NOTE_TYPE,
            self::INVOICED_OBJECT_SCHEME,
            self::VAT_DATE_CODE,
            self::PAYMENT_MEANS,
            self::ALLOWANCE_REASON,
            self::CHARGE_REASON,
            self::MIME,
            self::SEPA,
            self::VATEX,
        ];
    }

    /**
     * Returns all canonical values from a supported Peppol Billing code list.
     *
     * @param  string   $identifier One of this class's list identifiers.
     * @return string[]
     */
    public static function all(string $identifier): array
    {
        $codes = array_keys(self::codes($identifier));

        return array_map('strval', $codes);
    }

    /**
     * Checks exact membership in a supported Peppol Billing code list.
     *
     * @param  string      $identifier One of this class's list identifiers.
     * @param  string|null $code       Candidate code.
     * @return bool
     */
    public static function contains(string $identifier, ?string $code): bool
    {
        return $code !== null && array_key_exists($code, self::codes($identifier));
    }

    /**
     * Checks exact canonical spelling and membership without normalization.
     *
     * @param  string      $identifier One of this class's list identifiers.
     * @param  string|null $code       Candidate code.
     * @return bool
     */
    public static function isCanonical(string $identifier, ?string $code): bool
    {
        return self::contains($identifier, $code);
    }

    /**
     * Returns an exact code from the list, or null when it is unknown.
     *
     * @param  string      $identifier One of this class's list identifiers.
     * @param  string|null $code       Candidate code.
     * @return string|null
     */
    public static function resolve(string $identifier, ?string $code): ?string
    {
        return self::contains($identifier, $code) ? $code : null;
    }

    /**
     * Returns the Peppol name for an exact code, or null when it is unknown.
     *
     * @param  string      $identifier One of this class's list identifiers.
     * @param  string      $code       Candidate code.
     * @return string|null
     */
    public static function name(string $identifier, string $code): ?string
    {
        $codes = self::codes($identifier);

        return $codes[$code] ?? null;
    }

    /**
     * Returns code/name options suitable for a selection list.
     *
     * @param  string                    $identifier One of this class's list identifiers.
     * @return array<string|int, string>
     */
    public static function options(string $identifier): array
    {
        $options = [];

        foreach (self::codes($identifier) as $code => $name) {
            $options[$code] = $code . ' – ' . $name;
        }

        return $options;
    }

    /** Returns the release of the bundled Peppol BIS Billing code lists. */
    public static function release(): string
    {
        $release = self::resource()['peppol_release'] ?? null;

        return is_string($release) ? $release : CodeListResources::PEPPOL_BILLING_CODE_LISTS_RELEASE;
    }

    /**
     * Returns the source version for a code list, or null when the source does not publish one.
     *
     * @param  string      $identifier One of this class's list identifiers.
     * @return string|null
     */
    public static function version(string $identifier): ?string
    {
        $version = self::listMetadata($identifier)['version'] ?? null;

        return is_string($version) && trim($version) !== '' ? $version : null;
    }

    /**
     * Returns the source URL for a code list, or null for an unsupported identifier.
     *
     * @param  string      $identifier One of this class's list identifiers.
     * @return string|null
     */
    public static function source(string $identifier): ?string
    {
        $source = self::listMetadata($identifier)['source'] ?? null;

        return is_string($source) ? $source : null;
    }

    /** @return array<string, string> */
    private static function codes(string $identifier): array
    {
        if (isset(self::$codeMaps[$identifier])) {
            return self::$codeMaps[$identifier];
        }

        $rawCodes = self::listMetadata($identifier)['codes'] ?? [];

        if (! is_array($rawCodes)) {
            return self::$codeMaps[$identifier] = [];
        }

        $codes = [];

        // Keep only non-empty names so malformed resource entries cannot enter the typed code map.
        foreach ($rawCodes as $code => $name) {
            if (! is_string($name) || trim($name) === '') {
                continue;
            }

            $codes[(string) $code] = $name;
        }

        return self::$codeMaps[$identifier] = $codes;
    }

    /** @return array<string, mixed> */
    private static function listMetadata(string $identifier): array
    {
        $lists = self::resource()['lists'] ?? [];

        if (! is_array($lists) || ! is_array($lists[$identifier] ?? null)) {
            return [];
        }

        return $lists[$identifier];
    }

    /** @return array<string, mixed> */
    private static function resource(): array
    {
        return self::$resource ??= CodeListResources::readRequiredJsonArray(
            CodeListResources::PEPPOL_BILLING_CODE_LISTS_FILE
        );
    }
}
