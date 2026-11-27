<?php

namespace DreaM1\EfakturaConnector\Laravel\Cache;

use DateTime;
use Illuminate\Support\Facades\Cache;

/** Centralizes access to Laravel's default or explicitly selected cache store. @internal */
final class LaravelCacheStore
{
    private function __construct()
    {
    }

    /**
     * Reads a value from the selected cache store.
     *
     * @param  string|null $store Cache store name, or null to use Laravel's default store.
     * @param  string      $key   Cache key to read.
     * @return mixed       Stored value, or null when the key is absent.
     */
    public static function get(?string $store, string $key)
    {
        return $store === null ? Cache::get($key) : Cache::store($store)->get($key);
    }

    /**
     * Writes a value with an absolute expiry on the selected cache store.
     *
     * @param string|null $store     Cache store name, or null to use Laravel's default store.
     * @param string      $key       Cache key to write.
     * @param mixed       $value     Value to store.
     * @param DateTime    $expiresAt Absolute expiration time for the entry.
     */
    public static function put(?string $store, string $key, $value, DateTime $expiresAt): void
    {
        if ($store === null) {
            Cache::put($key, $value, $expiresAt);

            return;
        }

        Cache::store($store)->put($key, $value, $expiresAt);
    }

    /**
     * Removes a value from the selected cache store.
     *
     * @param string|null $store Cache store name, or null to use Laravel's default store.
     * @param string      $key   Cache key to remove.
     */
    public static function forget(?string $store, string $key): void
    {
        if ($store === null) {
            Cache::forget($key);

            return;
        }

        Cache::store($store)->forget($key);
    }

    /**
     * Reports whether the selected cache driver provides Laravel's atomic-lock contract.
     *
     * Laravel 5.6 does not expose the lock API. Drivers without a lock provider use the local
     * file-lock fallback in LaravelCacheLock instead.
     *
     * @param  string|null $store Cache store name, or null to use Laravel's default store.
     * @return bool        Whether the selected store supports atomic cache locks.
     */
    public static function supportsAtomicLocks(?string $store = null): bool
    {
        $repository = Cache::store($store);

        if (! is_object($repository)) {
            return false;
        }

        $cacheStore   = method_exists($repository, 'getStore') ? $repository->getStore() : $repository;
        $lockProvider = 'Illuminate\\Contracts\\Cache\\LockProvider';

        if (is_object($cacheStore) && interface_exists($lockProvider) && is_a($cacheStore, $lockProvider)) {
            return true;
        }

        return is_object($cacheStore) && method_exists($cacheStore, 'lock');
    }

    /** Returns Laravel's application root for local compatibility locks, when available. */
    public static function applicationDirectory(): ?string
    {
        $application = Cache::getFacadeApplication();

        if (is_object($application) && method_exists($application, 'basePath')) {
            $basePath = $application->basePath();

            return is_string($basePath) ? $basePath : null;
        }

        $workingDirectory = getcwd();

        return is_string($workingDirectory) ? $workingDirectory : null;
    }

    /**
     * Creates an atomic lock on the selected cache store.
     *
     * @param  string      $key          Shared cache key used to identify the lock.
     * @param  int         $leaseSeconds Maximum lock lifetime in seconds.
     * @param  string|null $store        Cache store name, or null to use Laravel's default store.
     * @return mixed       Laravel lock object returned by the selected store.
     */
    public static function lock(string $key, int $leaseSeconds, ?string $store = null)
    {
        return $store === null
            ? Cache::lock($key, $leaseSeconds)
            : Cache::store($store)->lock($key, $leaseSeconds);
    }
}
