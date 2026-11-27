<?php

namespace DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Transport\Coordination;

use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Transport\HttpExchangeRequest;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Http\Protocol\HttpHeaderName;
use DreaM1\EfakturaConnector\Support\HashAlgorithm;

/** Builds a non-sensitive provider/client key shared by requests across endpoint paths. */
final class HttpRequestBackpressureKey
{
    /**
     * Creates a stable, non-sensitive scope hash from a configured provider origin and credential identity.
     *
     * @param  string $baseUrl          Configured provider URL, which may not yet be validated.
     * @param  string ...$identityParts Stable credential identifiers; secret values are hashed immediately.
     * @return string SHA-256 scope hash for request coordination.
     */
    public static function scopeForConfiguredProvider(string $baseUrl, string ...$identityParts): string
    {
        return hash(
            HashAlgorithm::SHA_256,
            self::configuredOrigin($baseUrl) . "\0" . implode("\0", $identityParts)
        );
    }

    /**
     * Builds a stable hash from the request origin and configured client identity, without exposing either.
     *
     * @param  HttpExchangeRequest $request Request whose provider and credential scope is being keyed.
     * @return string              SHA-256 key suitable for cache or lock names.
     */
    public static function forRequest(HttpExchangeRequest $request): string
    {
        $baseUrl = $request->baseUrlOrNull();
        $url     = $baseUrl === null ? $request->path() : $baseUrl->value();

        $parts = parse_url($url);

        // Ignore path and query so different API routes for the same provider share one cooldown.
        $origin = is_array($parts)
            ? strtolower((string) ($parts['scheme'] ?? '')) . '://'
                . strtolower((string) ($parts['host'] ?? '')) . ':' . (string) ($parts['port'] ?? '')
            : $url;

        $scopeKey = $request->backpressureScopeKey();

        if ($scopeKey === null) {
            // Directly constructed requests lack configuration identity; hash authorization as a fallback.
            $authorizationHeaders = $request->headers()->allNamed(HttpHeaderName::authorization());
            $authorization        = isset($authorizationHeaders[0]) ? $authorizationHeaders[0]->value() : '';

            $scopeKey = hash(HashAlgorithm::SHA_256, $authorization);
        }

        return hash(HashAlgorithm::SHA_256, $origin . "\0" . $scopeKey);
    }

    /** Resolves a normalized origin while preserving deferred configuration validation. */
    private static function configuredOrigin(string $url): string
    {
        $parts = parse_url($url);

        if (! is_array($parts) || ! isset($parts['scheme'], $parts['host'])) {
            return $url;
        }

        $scheme = strtolower($parts['scheme']);
        $host   = strtolower($parts['host']);
        $port   = $parts['port'] ?? null;

        if (($scheme === 'https' && $port === 443) || ($scheme === 'http' && $port === 80)) {
            $port = null;
        }

        return $scheme . '://' . $host . ($port === null ? '' : ':' . $port);
    }
}
