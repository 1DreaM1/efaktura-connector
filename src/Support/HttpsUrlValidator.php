<?php

namespace DreaM1\EfakturaConnector\Support;

/** Shared HTTPS URL checks for callers with additional endpoint-specific rules. */
final class HttpsUrlValidator
{
    /**
     * Whether a URL is absolute and uses HTTPS, without applying endpoint-specific policy.
     *
     * @param string $url URL to inspect.
     */
    public static function isAbsoluteHttpsUrl(string $url): bool
    {
        $parts = parse_url($url);

        return filter_var($url, FILTER_VALIDATE_URL) !== false && self::hasSafeHttpsAuthority($parts);
    }

    /**
     * Whether parsed URL parts contain a safe HTTPS authority.
     *
     * @param mixed $parts Value returned by URL parsing.
     */
    public static function hasSafeHttpsAuthority($parts): bool
    {
        return is_array($parts)
            && ! empty($parts['host'])
            && ! isset($parts['user'])
            && ! isset($parts['pass'])
            && self::hasHttpsScheme($parts['scheme'] ?? null);
    }

    /**
     * Whether a parsed URL scheme is HTTPS, regardless of letter case.
     *
     * @param mixed $scheme Value returned by URL parsing.
     */
    public static function hasHttpsScheme($scheme): bool
    {
        return is_string($scheme) && strtolower($scheme) === 'https';
    }
}
