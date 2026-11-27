<?php

namespace DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Transport\Execution;

use DreaM1\EfakturaConnector\Core\Exceptions\ProviderConnectionException;
use DreaM1\EfakturaConnector\Core\Retry\RetryAfter;
use DreaM1\EfakturaConnector\Core\Retry\RetryPolicy;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Response\ProviderErrorEnvelopeParser;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Transport\Coordination\HttpRequestBackpressureInterface;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Transport\HttpExchangeRequest;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Transport\HttpExchangeResult;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Clock\ClockInterface;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Clock\SystemClock;
use Throwable;

/**
 * Repeats explicitly retry-safe requests after connection failures or retryable provider responses.
 *
 * Only 5xx and 429 responses are candidates, and the default decision requires the provider error
 * envelope to contain retryable: true. Hosts may replace that decision for another provider contract
 * through $mayRetryResult. The request's retry-safe flag defaults to GET only; maxAttempts includes
 * the initial call. Delays above maxInlineDelayMs are returned to the caller with the provider
 * response (or, after a connection failure, with the exception) so a host queue can schedule them without
 * tying up the current PHP worker.
 */
final class RetryingHttpRequestExecutor implements HttpRequestExecutorInterface
{
    /** Number of microseconds in one millisecond. */
    private const MICROSECONDS_PER_MILLISECOND = 1000;
    /** Number of milliseconds in one second. */
    private const MILLISECONDS_PER_SECOND = 1000;

    private HttpRequestExecutorInterface $executor;
    private RetryPolicy $policy;
    private ClockInterface $clock;

    /** @var callable(int): void */
    private $sleepFn;

    /** @var callable(HttpExchangeResult): bool */
    private $mayRetryResult;

    private ?HttpRequestBackpressureInterface $backpressure;

    /**
     * Creates a retrying executor with injectable delay and retry-decision callbacks.
     * @param HttpRequestExecutorInterface          $executor       Executor responsible for sending the HTTP request.
     * @param RetryPolicy                           $policy         Policy defining thresholds, limits, or timing behavior.
     * @param callable|null                         $sleepFn        Receives one delay in milliseconds and returns void; defaults to usleep().
     * @param callable|null                         $mayRetryResult Receives an HttpExchangeResult and returns whether the response
     *                                                              is retryable.
     * @param HttpRequestBackpressureInterface|null $backpressure   Store read after a 429 response so the delay also covers a
     *                                                              shared cooldown; recording it is the job of
     *                                                              RateLimitRecordingHttpRequestExecutor below this layer.
     * @param ClockInterface|null                   $clock          Time source for Retry-After and cooldown deadlines;
     *                                                              defaults to the system clock.
     */
    public function __construct(
        HttpRequestExecutorInterface $executor,
        RetryPolicy $policy,
        ?callable $sleepFn = null,
        ?callable $mayRetryResult = null,
        ?HttpRequestBackpressureInterface $backpressure = null,
        ?ClockInterface $clock = null
    ) {
        $this->executor = $executor;
        $this->policy   = $policy;
        $this->clock    = $clock ?? new SystemClock();
        $this->sleepFn  = $sleepFn ?? static function (int $ms): void {
            usleep($ms * self::MICROSECONDS_PER_MILLISECOND);
        };
        $this->mayRetryResult = $mayRetryResult ?? static function (HttpExchangeResult $result): bool {
            $error = ProviderErrorEnvelopeParser::parse($result->body());

            return $error !== null && $error->retryable() === true;
        };
        $this->backpressure = $backpressure;
    }

    /**
     * Executes the prepared HTTP request, replaying it only when its operation is marked safe.
     *
     * When another attempt remains, a ProviderConnectionException is retried after a synchronous policy delay
     * no longer than maxInlineDelayMs; a longer delay rethrows the exception.
     * Response-based retries longer than maxInlineDelayMs are returned to the caller.
     * For HTTP responses, retries additionally require an eligible status and a positive provider
     * retry decision; ordinary client errors are returned to the caller unchanged.
     *
     * @param  HttpExchangeRequest $request Prepared HTTP request to execute under its retry-safety policy.
     * @return HttpExchangeResult
     * @throws Throwable           when the wrapped executor or a configured retry callback fails.
     */
    public function execute(HttpExchangeRequest $request): HttpExchangeResult
    {
        if (! $request->isRetrySafe()) {
            // A write may have succeeded even when its response was lost, so unsafe requests get one attempt.
            return $this->executor->execute($request);
        }

        $maxAttempts = $this->policy->maxAttempts();
        for ($attempt = 1;; $attempt++) {
            try {
                $result = $this->executor->execute($request);
            } catch (ProviderConnectionException $exception) {
                // Retry transport failures only while a safe request still has attempts available.
                if ($attempt >= $maxAttempts) {
                    throw $exception;
                }

                // No response exists to supply Retry-After, so use the configured exponential backoff.
                $delayMs = $this->policy->delayForRetry($attempt);

                if ($delayMs > $this->policy->maxInlineDelayMs()) {
                    // A wait longer than the inline limit is left to the host instead of blocking this worker.
                    throw $exception;
                }

                ($this->sleepFn)($delayMs);
                continue;
            }

            if ($attempt >= $maxAttempts || ! $this->isRetryableResult($result)) {
                return $result;
            }

            $delayMs = $this->retryDelayForResponse($request, $result, $attempt);

            if ($delayMs === null || $delayMs > $this->policy->maxInlineDelayMs()) {
                // Return long waits with the provider response for host scheduling instead of sleeping in PHP.
                return $result;
            }

            ($this->sleepFn)($delayMs);
        }
    }

    /** Checks retryable status codes against the request-specific safety policy. */
    private function isRetryableResult(HttpExchangeResult $result): bool
    {
        return ($result->isServerError() || $result->isTooManyRequests())
            && ($this->mayRetryResult)($result);
    }

    /** Uses a valid Retry-After value or falls back to the configured backoff. */
    private function retryDelayForResponse(HttpExchangeRequest $request, HttpExchangeResult $result, int $attempt): ?int
    {
        $retryAfterDelayMs = $this->retryAfterDelayMs($result);

        if ($retryAfterDelayMs !== null && $retryAfterDelayMs > $this->policy->maxDelayMs()) {
            // Do not cap Retry-After to a shorter inline delay; return the response for caller handling.
            return null;
        }

        $delayMs = $retryAfterDelayMs ?? $this->policy->delayForRetry($attempt);

        $cooldownUntil = $result->isTooManyRequests() && $this->backpressure !== null
            ? $this->backpressure->blockedUntil($request)
            : null;

        if ($cooldownUntil !== null) {
            // Respect both Retry-After and any shared 429 cooldown recorded by this or another request.
            $cooldownSeconds = max(0, $cooldownUntil->unixTimestamp() - $this->clock->now());
            $delayMs         = max($delayMs, $this->secondsToMilliseconds($cooldownSeconds));
        }

        return $delayMs;
    }

    /** Parses Retry-After seconds or an HTTP date into a non-negative delay. */
    private function retryAfterDelayMs(HttpExchangeResult $result): ?int
    {
        $now     = $this->clock->now();
        $retryAt = RetryAfter::deadline($result->headers(), $now);

        if ($retryAt === null) {
            return null;
        }

        $seconds = max(0, $retryAt->unixTimestamp() - $now);
        return $this->secondsToMilliseconds($seconds);
    }

    /** Converts a non-negative delay without overflowing the platform integer. */
    private function secondsToMilliseconds(int $seconds): int
    {
        return $seconds > intdiv(PHP_INT_MAX, self::MILLISECONDS_PER_SECOND)
            ? PHP_INT_MAX
            : $seconds * self::MILLISECONDS_PER_SECOND;
    }
}
