<?php

namespace DreaM1\EfakturaConnector\Infrastructure\EFaktura\Validation;

use DreaM1\EfakturaConnector\Infrastructure\ResourcePath;

/** Versions and filesystem paths for validation artifacts distributed with the package. */
final class ValidationResources
{
    /** Version of the Saxon runtime bundled for Schematron validation. */
    public const SAXON_VERSION = '12.10';
    /** Minimum Java version required to run local Schematron validation. */
    public const MINIMUM_JAVA_VERSION = 11;
    /** Version of the bundled CEN/EN 16931 validation rules. */
    public const CEN_RULES_VERSION = '1.3.17';
    /** Version of the bundled Peppol validation rules. */
    public const PEPPOL_RULES_VERSION = '3.0.22';
    /** OpenPeppol release represented by the bundled Peppol validation rules. */
    public const PEPPOL_RULES_RELEASE = '2026-Q4-MR';
    /** Stable identifier used for CEN/EN 16931 rule-set metadata. */
    public const CEN_RULESET_ID = 'cen_en16931';
    /** Filename of the bundled validation resource manifest. */
    public const RULES_MANIFEST_FILENAME = 'rules-manifest.json';
    /** Provenance key for the validation manifest's SHA-256 fingerprint. */
    public const MANIFEST_ARTIFACT_PROVENANCE_KEY = 'artifact.validation_manifest.sha256';
    /** Versioned directories for the independently released Schematron rule sets. */
    public const CEN_RULES_DIRECTORY = 'cen-' . self::CEN_RULES_VERSION;
    /** Directory containing the bundled Peppol rules for the configured release. */
    public const PEPPOL_RULES_DIRECTORY = 'peppol-' . self::PEPPOL_RULES_VERSION . '-' . self::PEPPOL_RULES_RELEASE;
    /** Ruleset identifier used for the CEN/EN 16931 stylesheet. */
    public const CEN_RULESET = 'CEN-EN16931-UBL';
    /** Ruleset identifier used for the Peppol Billing stylesheet. */
    public const PEPPOL_RULESET = 'PEPPOL-EN16931-UBL';
    /** EN16931 baseline followed by Peppol rules, which may narrow baseline code lists. */
    public const PEPPOL_RULESETS = [self::CEN_RULESET, self::PEPPOL_RULESET];
    /** @var string[] Maps each rule set to its independently versioned resource directory. */
    public const RULESET_DIRECTORIES = [
        self::CEN_RULESET    => self::CEN_RULES_DIRECTORY,
        self::PEPPOL_RULESET => self::PEPPOL_RULES_DIRECTORY,
    ];

    /**
     * Returns the directory containing bundled validation resources.
     *
     * @return string
     */
    public static function directory(): string
    {
        return ResourcePath::packageDirectory('resources/validation');
    }

    /**
     * Resolves a relative path inside the bundled validation resources.
     *
     * @param  string $relativePath Path relative to the configured resource directory.
     * @return string
     */
    public static function path(string $relativePath): string
    {
        return ResourcePath::join(self::directory(), $relativePath);
    }

    /**
     * Returns the compiled Schematron stylesheets in execution order.
     *
     * @param  string|null $resourceDirectory Optional package validation resource root
     * @return string[]
     */
    public static function schematronStylesheets(?string $resourceDirectory = null): array
    {
        $resourceDirectory = $resourceDirectory ?? self::directory();
        $stylesheets       = [];

        foreach (self::PEPPOL_RULESETS as $ruleset) {
            $stylesheets[] = self::stylesheet($ruleset, $resourceDirectory);
        }

        return $stylesheets;
    }

    /**
     * Returns the standalone CEN stylesheet path.
     *
     * @param  string|null $resourceDirectory Optional root directory containing validation resources.
     * @return string      Absolute path to the CEN Schematron stylesheet.
     */
    public static function cenStylesheet(?string $resourceDirectory = null): string
    {
        return self::stylesheet(
            self::CEN_RULESET,
            $resourceDirectory ?? self::directory()
        );
    }

    /**
     * Resolves the stylesheet path for a ruleset known to this class.
     *
     * @param  string $ruleset           Ruleset identifier from the configured ruleset map.
     * @param  string $resourceDirectory Root directory containing versioned validation resources.
     * @return string Absolute path to the compiled stylesheet.
     */
    private static function stylesheet(string $ruleset, string $resourceDirectory): string
    {
        $directory = ResourcePath::join($resourceDirectory, self::RULESET_DIRECTORIES[$ruleset]);

        return ResourcePath::join($directory, $ruleset . '.xsl');
    }
}
