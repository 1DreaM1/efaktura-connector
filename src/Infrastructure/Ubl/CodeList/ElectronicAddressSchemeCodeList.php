<?php

namespace DreaM1\EfakturaConnector\Infrastructure\Ubl\CodeList;

/** EAS codes accepted by Peppol Billing, narrower than the CEN BR-CL-25 baseline. */
final class ElectronicAddressSchemeCodeList
{
    /** Number of digits in a Peppol EAS scheme code. */
    private const SCHEME_CODE_LENGTH = 4;
    /** JSON resource key containing the supported EAS scheme values. */
    private const RESOURCE_FIELD_SCHEMES = 'schemes';

    /** @var array<string, mixed>|null */
    private static ?array $resource = null;

    /** @var string[]|null */
    private static ?array $schemeCodes = null;

    /**
     * Returns whether the value is an exact code from the bundled EAS list.
     *
     * @param  string $scheme Endpoint identifier scheme code.
     * @return bool   Whether the code is present in the bundled list.
     */
    public static function isCanonical(string $scheme): bool
    {
        return in_array($scheme, self::schemes(), true);
    }

    /**
     * Returns whether an EndpointID has a listed scheme and a non-empty value.
     *
     * @param  string|null $scheme Endpoint identifier scheme code.
     * @param  string      $value  Endpoint identifier value.
     * @return bool        Whether the scheme is supported and the value is non-empty.
     */
    public static function isValidEndpoint(?string $scheme, string $value): bool
    {
        return $scheme !== null
            && self::isCanonical($scheme)
            && trim($value) !== '';
    }

    /**
     * Returns all supported Electronic Address Scheme codes.
     *
     * @return string[]
     */
    public static function all(): array
    {
        return self::schemes();
    }

    /** @return string[] */
    private static function schemes(): array
    {
        if (self::$schemeCodes === null) {
            $schemes      = self::resource()[self::RESOURCE_FIELD_SCHEMES] ?? [];
            $validSchemes = is_array($schemes)
                ? array_filter($schemes, static function ($scheme) {
                    return is_string($scheme)
                        && preg_match('/^[0-9]{' . self::SCHEME_CODE_LENGTH . '}$/D', $scheme) === 1;
                })
                : [];

            self::$schemeCodes = array_values($validSchemes);
        }

        return self::$schemeCodes;
    }

    /** @return array<string, mixed> */
    private static function resource(): array
    {
        return self::$resource ??= CodeListResources::readRequiredJsonArray(
            CodeListResources::ELECTRONIC_ADDRESS_SCHEMES_FILE
        );
    }
}
