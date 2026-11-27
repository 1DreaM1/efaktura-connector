<?php

namespace DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Transport\Execution;

use DreaM1\EfakturaConnector\Core\Configuration\ConnectorRuntimeConfiguration;
use DreaM1\EfakturaConnector\Core\Retry\RetryAfter;
use DreaM1\EfakturaConnector\Core\Retry\RetryDeadline;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Transport\Coordination\HttpRequestBackpressureInterface;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Transport\HttpExchangeRequest;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Transport\HttpExchangeResult;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Clock\ClockInterface;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Clock\SystemClock;
use DreaM1\EfakturaConnector\Support\Exceptions\InvalidInputException;

/**
 * Stores a shared provider cooldown as soon as an attempt receives HTTP 429.
 *
 * It sits below the retry layer, so every attempt is recorded exactly once and the retry layer can read
 * the stored deadline before deciding how long to wait. The deadline is a valid Retry-After value or,
 * when the response has none, the configured fallback cooldown, in both cases limited to MAX_COOLDOWN_SECONDS.
 */
final class RateLimitRecordingHttpRequestExecutor implements HttpRequestExecutorInterface
{
    /**
     * Longest shared cooldown this layer records, in seconds. A longer Retry-After (or fallback) is shortened to
     * this value; if the provider is still limiting afterwards, the next 429 records a new cooldown.
     */
    public const MAX_COOLDOWN_SECONDS = 3600;

    private HttpRequestExecutorInterface $executor;
    private HttpRequestBackpressureInterface $backpressure;
    private int $fallbackCooldownSeconds;
    private ClockInterface $clock;

    /**
     * Wraps an executor and records rate-limit deadlines from its responses.
     *
     * @param  HttpRequestExecutorInterface     $executor                Executor that performs the HTTP exchange.
     * @param  HttpRequestBackpressureInterface $backpressure            Store for provider rate-limit deadlines.
     * @param  int                              $fallbackCooldownSeconds Cooldown when a 429 response has no valid Retry-After.
     * @param  ClockInterface|null              $clock                   Time source for deadlines; defaults to the system clock.
     * @throws InvalidInputException            when the fallback cooldown is less than one second
     */
    public function __construct(
        HttpRequestExecutorInterface $executor,
        HttpRequestBackpressureInterface $backpressure,
        int $fallbackCooldownSeconds = ConnectorRuntimeConfiguration::DEFAULT_HTTP_RATE_LIMIT_COOLDOWN_SECONDS,
        ?ClockInterface $clock = null
    ) {
        if ($fallbackCooldownSeconds < 1) {
            throw new InvalidInputException('The rate limit fallback cooldown must be positive.');
        }

        $this->executor                = $executor;
        $this->backpressure            = $backpressure;
        $this->fallbackCooldownSeconds = $fallbackCooldownSeconds;
        $this->clock                   = $clock ?? new SystemClock();
    }

    /**
     * Executes the request and records a cooldown when the response is HTTP 429.
     *
     * @param  HttpExchangeRequest $request Request to execute.
     * @return HttpExchangeResult  Unmodified exchange returned by the wrapped executor.
     */
    public function execute(HttpExchangeRequest $request): HttpExchangeResult
    {
        $result = $this->executor->execute($request);

        if ($result->isTooManyRequests()) {
            $now     = $this->clock->now();
            $retryAt = RetryAfter::deadline($result->headers(), $now) ?? RetryDeadline::fromUnixTimestamp(
                $this->fallbackCooldownSeconds > PHP_INT_MAX - $now
                    ? PHP_INT_MAX
                    : $now + $this->fallbackCooldownSeconds
            );

            // The stored cooldown can only grow, so an absurd Retry-After (a misconfigured gateway, a wrong
            // unit) would otherwise block every request in the scope until the cache entry is deleted by hand.
            $latestAllowed = $now > PHP_INT_MAX - self::MAX_COOLDOWN_SECONDS
                ? PHP_INT_MAX
                : $now + self::MAX_COOLDOWN_SECONDS;

            if ($retryAt->isAfter($latestAllowed)) {
                $retryAt = RetryDeadline::fromUnixTimestamp($latestAllowed);
            }

            $this->backpressure->deferUntil($request, $retryAt);
        }

        return $result;
    }
}
