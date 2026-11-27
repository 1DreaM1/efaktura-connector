<?php

namespace DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Transport\Coordination;

use DreaM1\EfakturaConnector\Core\Retry\RetryDeadline;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Transport\HttpExchangeRequest;

/** Process-local cooldown storage for standalone use and the default non-framework stack. */
final class InMemoryHttpRequestBackpressure implements HttpRequestBackpressureInterface
{
    /** @var array<string, RetryDeadline> */
    private array $deadlines = [];
    /** @var callable(): int */
    private $clock;

    /**
     * Creates process-local cooldown storage with an optional clock for deterministic use.
     *
     * @param callable|null $clock Zero-argument callback returning the current Unix timestamp in seconds; defaults to time().
     */
    public function __construct(?callable $clock = null)
    {
        $this->clock = $clock ?? static function (): int {
            return time();
        };
    }

    /**
     * Returns the active process-local deadline for this provider and credential scope.
     *
     * @param  HttpExchangeRequest $request Request used to derive the scope key.
     * @return RetryDeadline|null  Time after which the request may proceed, or null when not deferred.
     */
    public function blockedUntil(HttpExchangeRequest $request): ?RetryDeadline
    {
        $key     = HttpRequestBackpressureKey::forRequest($request);
        $retryAt = $this->deadlines[$key] ?? null;

        if ($retryAt !== null && ! $retryAt->isAfter(($this->clock)())) {
            unset($this->deadlines[$key]);

            return null;
        }

        return $retryAt;
    }

    /**
     * Extends the process-local deadline without shortening a later existing deadline.
     *
     * @param HttpExchangeRequest $request Request used to derive the scope key.
     * @param RetryDeadline       $retryAt Earliest time at which requests in this scope may proceed.
     */
    public function deferUntil(HttpExchangeRequest $request, RetryDeadline $retryAt): void
    {
        $key     = HttpRequestBackpressureKey::forRequest($request);
        $current = $this->deadlines[$key] ?? null;

        if ($current === null || $retryAt->isAfter($current->unixTimestamp())) {
            $this->deadlines[$key] = $retryAt;
        }
    }
}
