<?php

namespace DreaM1\EfakturaConnector\Laravel\Transport;

use DreaM1\EfakturaConnector\Core\Exceptions\RequestCoordinationException;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Transport\Coordination\ScopedHttpRequestExecutionLockInterface;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Transport\HttpExchangeResult;
use DreaM1\EfakturaConnector\Support\Exceptions\InvalidInputException;
use DreaM1\EfakturaConnector\Support\HashAlgorithm;
use Throwable;

/** Coordinates provider-scoped HTTP execution through Laravel cache locks or a local file fallback. */
final class LaravelCacheHttpRequestExecutionLock implements ScopedHttpRequestExecutionLockInterface
{
    /** Prefix for hashed cache keys storing provider-scoped execution locks. */
    private const CACHE_KEY_PREFIX = 'efaktura.http_execution.';

    private ?string $store;
    private int $leaseSeconds;
    private int $waitSeconds;

    /**
     * Creates a lock adapter backed by the configured Laravel cache store when it supports locks.
     *
     * @param  string|null           $store        Cache store name; null uses Laravel's default cache store.
     * @param  int                   $leaseSeconds Cache-lock lease in seconds; unused by the local file fallback.
     * @param  int                   $waitSeconds  Maximum time to wait for a competing request lock.
     * @throws InvalidInputException when the lease is less than one second or wait time is negative.
     */
    public function __construct(?string $store = null, int $leaseSeconds = 180, int $waitSeconds = 120)
    {
        if ($leaseSeconds < 1) {
            throw new InvalidInputException('The HTTP execution lock lease must be positive.');
        }

        if ($waitSeconds < 0) {
            throw new InvalidInputException('The HTTP execution lock wait time must not be negative.');
        }

        $this->store        = $store;
        $this->leaseSeconds = $leaseSeconds;
        $this->waitSeconds  = $waitSeconds;
    }

    /**
     * Runs one request while holding an atomic cache lock for its provider rate-limit scope.
     *
     * @param  string                         $scopeKey Stable non-sensitive provider scope key.
     * @param  callable(): HttpExchangeResult $callback HTTP execution protected by this lock.
     * @return HttpExchangeResult
     * @throws InvalidInputException          when the scope key is blank.
     * @throws RequestCoordinationException   when Laravel or the local file system cannot acquire the lock.
     * @throws Throwable                      when the guarded request callback fails.
     */
    public function synchronizedFor(string $scopeKey, callable $callback): HttpExchangeResult
    {
        if (trim($scopeKey) === '') {
            throw new InvalidInputException('The HTTP execution lock scope key must not be empty.');
        }

        $key = self::CACHE_KEY_PREFIX . hash(HashAlgorithm::SHA_256, $scopeKey);

        try {
            $release = LaravelCacheLock::acquire(
                $key,
                $this->leaseSeconds,
                $this->waitSeconds,
                $this->store
            );
        } catch (Throwable $exception) {
            throw new RequestCoordinationException($exception);
        }

        try {
            return $callback();
        } finally {
            $release();
        }
    }
}
