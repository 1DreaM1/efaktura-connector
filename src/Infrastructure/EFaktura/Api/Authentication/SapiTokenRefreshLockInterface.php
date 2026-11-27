<?php

namespace DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Authentication;

use Throwable;

/**
 * Coordinates token refreshes across workers sharing a token cache.
 *
 * @api
 */
interface SapiTokenRefreshLockInterface
{
    /**
     * Runs the operation while holding a refresh lock when coordination is available.
     *
     * Implementations must invoke the operation exactly once, even when locking fails or times out.
     *
     * @param  string            $cacheKey     Cache key identifying the SAPI token
     * @param  int               $leaseSeconds Maximum lifetime for a backend lock that uses leases
     * @param  int               $waitSeconds  Maximum time to wait for another worker's lock
     * @param  callable(): mixed $operation    Token recheck and refresh operation
     * @return mixed
     * @throws Throwable         when the token operation fails
     */
    public function synchronized(string $cacheKey, int $leaseSeconds, int $waitSeconds, callable $operation);
}
