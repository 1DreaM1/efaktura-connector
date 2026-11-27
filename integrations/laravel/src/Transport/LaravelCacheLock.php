<?php

namespace DreaM1\EfakturaConnector\Laravel\Transport;

use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Coordination\FileLock;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Coordination\LockStorageException;
use DreaM1\EfakturaConnector\Laravel\Cache\LaravelCacheStore;
use Throwable;

/** Provides internal atomic-lock helpers for Laravel cache operations. @internal */
final class LaravelCacheLock
{
    /** Prefix separating Laravel compatibility locks from other connector file locks. */
    private const FILE_LOCK_DIRECTORY_PREFIX = 'efaktura-connector-laravel-';
    /** Default lease duration for short-lived Laravel cache locks, in seconds. */
    private const LEASE_SECONDS = 5;
    /** Default wait duration for short-lived Laravel cache locks, in seconds. */
    private const WAIT_SECONDS = 2;

    /** Prevents instances; callers use the static synchronized operation. */
    private function __construct()
    {
    }

    /**
     * Runs the callback under an atomic cache lock without allowing release errors to mask its result.
     *
     * @param  string            $key          Shared Laravel cache key used as the lock identity.
     * @param  callable(): mixed $operation    Operation to execute while holding the lock.
     * @param  int               $leaseSeconds Maximum lock lifetime, in seconds.
     * @param  int               $waitSeconds  Maximum time to wait for the lock, in seconds.
     * @param  string|null       $store        Cache store name, or null to use Laravel's default store.
     * @return mixed             Result returned by the operation.
     * @throws Throwable         when lock acquisition or the operation fails.
     */
    public static function synchronized(
        string $key,
        callable $operation,
        int $leaseSeconds = self::LEASE_SECONDS,
        int $waitSeconds = self::WAIT_SECONDS,
        ?string $store = null
    ) {
        $release = self::acquire($key, $leaseSeconds, $waitSeconds, $store);

        try {
            return $operation();
        } finally {
            $release();
        }
    }

    /**
     * Runs the operation under the selected store using the default short-lived lock policy.
     *
     * @param  string            $key       Shared Laravel cache key used as the lock identity.
     * @param  callable(): mixed $operation Operation to execute while holding the lock.
     * @param  string|null       $store     Cache store name, or null to use Laravel's default store.
     * @return mixed             Result returned by the operation.
     * @throws Throwable         when lock acquisition or the operation fails.
     */
    public static function synchronizedOnStore(string $key, callable $operation, ?string $store = null)
    {
        return self::synchronized($key, $operation, self::LEASE_SECONDS, self::WAIT_SECONDS, $store);
    }

    /**
     * Runs the operation without a lock if acquiring an atomic cache lock fails.
     * Concurrent operations are possible on this best-effort path.
     * Operation exceptions still propagate to the caller; release errors are ignored.
     *
     * @param  string            $key              Shared Laravel cache key used as the lock identity.
     * @param  callable(): mixed $operation        Operation to execute.
     * @param  int               $leaseSeconds     Maximum lock lifetime, in seconds.
     * @param  int               $waitSeconds      Maximum time to wait for the lock, in seconds.
     * @param  string|null       $store            Cache store name, or null to use Laravel's default store.
     * @param  callable|null     $onAcquireFailure Called when the lock could not be acquired and the operation runs unlocked.
     * @return mixed             Result returned by the operation.
     * @throws Throwable         when the operation fails.
     */
    public static function synchronizedBestEffort(
        string $key,
        callable $operation,
        int $leaseSeconds = self::LEASE_SECONDS,
        int $waitSeconds = self::WAIT_SECONDS,
        ?string $store = null,
        ?callable $onAcquireFailure = null
    ) {
        try {
            $release = self::acquire($key, $leaseSeconds, $waitSeconds, $store);
        } catch (Throwable $exception) {
            if ($onAcquireFailure !== null) {
                $onAcquireFailure();
            }

            return $operation();
        }

        try {
            return $operation();
        } finally {
            $release();
        }
    }

    /**
     * @internal Acquires a Laravel atomic lock and returns its cleanup callback.
     *
     * @param  string           $key          Shared Laravel cache key used as the lock identity.
     * @param  int              $leaseSeconds Maximum lock lifetime, in seconds.
     * @param  int              $waitSeconds  Maximum time to wait for the lock, in seconds.
     * @param  string|null      $store        Cache store name, or null to use Laravel's default store.
     * @return callable(): void Releases the lock without propagating cleanup errors.
     * @throws Throwable        when the selected cache or file lock cannot be acquired.
     */
    public static function acquire(
        string $key,
        int $leaseSeconds,
        int $waitSeconds,
        ?string $store = null
    ): callable {
        if (! LaravelCacheStore::supportsAtomicLocks($store)) {
            $fileLock = new FileLock(
                LaravelCacheStore::applicationDirectory(),
                null,
                self::FILE_LOCK_DIRECTORY_PREFIX
            );
            $resource = $fileLock->acquire($key, max(0, $waitSeconds));

            if ($resource === null) {
                throw new LockStorageException('The local compatibility lock could not be acquired before timeout.');
            }

            return static function () use ($resource): void {
                $resource->release();
            };
        }

        $cacheLock = LaravelCacheStore::lock($key, $leaseSeconds, $store);
        $cacheLock->block(max(0, $waitSeconds));

        return static function () use ($cacheLock): void {
            try {
                $cacheLock->release();
            } catch (Throwable $exception) {
                // Lock cleanup must not replace the protected operation result.
            }
        };
    }
}
