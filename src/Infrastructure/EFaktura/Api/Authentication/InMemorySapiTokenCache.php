<?php

namespace DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Authentication;

/** @internal Process-local token cache used by the framework-independent connector factory. */
final class InMemorySapiTokenCache implements SapiTokenCacheInterface
{
    /** @var array<string, SapiToken> */
    private array $tokens = [];

    /** {@inheritdoc} */
    public function load(string $key): ?SapiToken
    {
        return $this->tokens[$key] ?? null;
    }

    /** {@inheritdoc} */
    public function store(string $key, SapiToken $token): void
    {
        $this->tokens[$key] = $token;
    }

    /** {@inheritdoc} */
    public function forget(string $key): void
    {
        unset($this->tokens[$key]);
    }
}
