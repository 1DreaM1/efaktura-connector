<?php

namespace DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Authentication;

/**
 * @api Persistence boundary for sensitive SAPI OAuth access tokens.
 *
 * SapiTokenProvider supplies an opaque key derived from the provider URL, client credentials, and
 * requested scope. Implementations should keep the token value and its expiry and protect the token
 * value at rest. Expiration is checked by the provider after loading, and forgetting an entry does
 * not revoke the token remotely.
 */
interface SapiTokenCacheInterface
{
    /**
     * Returns a cached token, or null when the key is absent.
     *
     * @param  string         $key Opaque cache key supplied by the provider.
     * @return SapiToken|null
     */
    public function load(string $key): ?SapiToken;

    /**
     * Stores the token using the caller-provided cache key.
     *
     * @param  string    $key   Opaque cache key supplied by the provider.
     * @param  SapiToken $token SAPI token value to store.
     * @return void
     */
    public function store(string $key, SapiToken $token): void;

    /**
     * Removes the cached token for a key; an absent entry is not an error.
     *
     * @param  string $key Opaque cache key supplied by the provider.
     * @return void
     */
    public function forget(string $key): void;
}
