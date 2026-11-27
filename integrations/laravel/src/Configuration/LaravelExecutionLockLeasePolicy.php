<?php

namespace DreaM1\EfakturaConnector\Laravel\Configuration;

use DreaM1\EfakturaConnector\Core\Configuration\HttpRuntimeConfigurationInterface;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Transport\Execution\CurlRequestExecutor;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Transport\Execution\PooledCurlRequestExecutor;
use DreaM1\EfakturaConnector\Support\Exceptions\InvalidInputException;

/** Resolves and validates the cache execution-lock lease so it covers one bounded cURL attempt. */
final class LaravelExecutionLockLeasePolicy
{
    /** Safety margin added to the maximum duration of one HTTP attempt, in seconds. */
    private const EXECUTION_LOCK_LEASE_MARGIN_SECONDS = 5;

    /**
     * Returns the cache lock lease: the configured value, or the minimum that covers one cURL attempt.
     *
     * The lock is held for one attempt and released before a retry delay, so retry settings do not affect the
     * required lease. Custom executors do not expose a maximum runtime through HttpRequestExecutorInterface,
     * so this policy cannot safely derive their lease requirement.
     *
     * @param  int|null                          $configuredLeaseSeconds Explicit lease in seconds; null derives it.
     * @param  string                            $requestExecutorClass   Configured base HTTP executor class.
     * @param  HttpRuntimeConfigurationInterface $runtime                HTTP timeout settings.
     * @return int                               Lease in seconds, at least one request timeout plus the margin.
     * @throws InvalidInputException             when the executor has no known bound or the lease is too short.
     */
    public static function resolveCacheLeaseSeconds(
        ?int $configuredLeaseSeconds,
        string $requestExecutorClass,
        HttpRuntimeConfigurationInterface $runtime
    ): int {
        if (! in_array($requestExecutorClass, [CurlRequestExecutor::class, PooledCurlRequestExecutor::class], true)) {
            throw new InvalidInputException(
                'The cache execution lock lease cannot be validated for a custom HTTP request executor.'
            );
        }

        $minimumLeaseSeconds = $runtime->httpRequestTimeoutSeconds() + self::EXECUTION_LOCK_LEASE_MARGIN_SECONDS;

        if ($configuredLeaseSeconds === null) {
            return $minimumLeaseSeconds;
        }

        if ($configuredLeaseSeconds < $minimumLeaseSeconds) {
            throw new InvalidInputException(sprintf(
                'The HTTP execution lock lease must be at least %d seconds for the configured runtime.',
                $minimumLeaseSeconds
            ));
        }

        return $configuredLeaseSeconds;
    }
}
