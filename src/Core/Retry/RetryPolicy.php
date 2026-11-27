<?php

namespace DreaM1\EfakturaConnector\Core\Retry;

use DreaM1\EfakturaConnector\Support\Exceptions\InvalidInputException;

/**
 * Immutable limits and delays used when retrying transient HTTP failures.
 *
 * maxAttempts counts the initial call as attempt one. Delays are exponential between retries,
 * capped at maxDelayMs; they do not add attempts or imply that a request is safe to replay. The delay
 * ceiling is also bounded so converting milliseconds to the default sleeper's microseconds cannot overflow.
 */
final class RetryPolicy
{
    /** Upper bound for total attempts, including the initial request. */
    public const MAX_ATTEMPTS = 10;
    /** Default total attempts, including the initial request. */
    public const DEFAULT_MAX_ATTEMPTS = 3;
    /** Default delay before the first retry, in milliseconds. */
    public const DEFAULT_BASE_DELAY_MS = 500;
    /** Default upper bound for a calculated retry delay, in milliseconds. */
    public const DEFAULT_MAX_DELAY_MS = 30_000;
    /** Default maximum retry delay held synchronously before returning an HTTP response, in milliseconds. */
    public const DEFAULT_MAX_INLINE_DELAY_MS = 1_000;

    private int $maxAttempts;
    private int $baseDelayMs;
    private int $maxDelayMs;
    private int $maxInlineDelayMs;

    /**
     * Creates a retry policy.
     *
     * @param  int                   $maxAttempts      Total calls allowed, including the initial call
     * @param  int                   $baseDelayMs      Delay before retry one, in milliseconds
     * @param  int                   $maxDelayMs       Upper bound for each calculated delay, in milliseconds
     * @param  int|null              $maxInlineDelayMs Maximum retry delay held synchronously before returning the response or rethrowing the connection failure
     * @throws InvalidInputException when attempts or delays are outside the supported range.
     */
    public function __construct(int $maxAttempts, int $baseDelayMs, int $maxDelayMs, ?int $maxInlineDelayMs = null)
    {
        if ($maxAttempts < 1 || $maxAttempts > self::MAX_ATTEMPTS) {
            throw new InvalidInputException(
                'Retry policy must allow between one and ' . self::MAX_ATTEMPTS . ' total attempts.'
            );
        }

        if ($baseDelayMs < 0 || $maxDelayMs < 0 || ($maxInlineDelayMs !== null && $maxInlineDelayMs < 0)) {
            throw new InvalidInputException('Retry delays must not be negative.');
        }

        // The default executor converts milliseconds to microseconds for usleep().
        if ($maxDelayMs > intdiv(PHP_INT_MAX, 1000)) {
            throw new InvalidInputException('Maximum retry delay is too large for safe microsecond conversion.');
        }

        $this->maxAttempts      = $maxAttempts;
        $this->baseDelayMs      = $baseDelayMs;
        $this->maxDelayMs       = $maxDelayMs;
        $this->maxInlineDelayMs = $maxInlineDelayMs ?? $maxDelayMs;
    }

    /**
     * Creates the default policy: three total attempts, starting at 500 ms, capped at 30 seconds,
     * and returning responses with Retry-After delays over one second to the host for later scheduling.
     */
    public static function default(): self
    {
        return new self(
            self::DEFAULT_MAX_ATTEMPTS,
            self::DEFAULT_BASE_DELAY_MS,
            self::DEFAULT_MAX_DELAY_MS,
            self::DEFAULT_MAX_INLINE_DELAY_MS
        );
    }

    /**
     * Returns the total number of attempts allowed, including the first call.
     *
     * @return int
     */
    public function maxAttempts(): int
    {
        return $this->maxAttempts;
    }

    /**
     * Returns the maximum delay between attempts, in milliseconds.
     *
     * @return int
     */
    public function maxDelayMs(): int
    {
        return $this->maxDelayMs;
    }

    /** Returns the largest retry delay that the executor may wait inline, in milliseconds. */
    public function maxInlineDelayMs(): int
    {
        return $this->maxInlineDelayMs;
    }

    /**
     * Returns the exponential delay before retry number $retryNumber (one-indexed).
     *
     * For example, retry one uses the base delay, retry two doubles it, and retry three
     * quadruples it, subject to the configured maximum. The initial call has no delay.
     *
     * @param  int                   $retryNumber One-based retry number used to calculate the delay.
     * @return int
     * @throws InvalidInputException when the retry number is less than one.
     */
    public function delayForRetry(int $retryNumber): int
    {
        if ($retryNumber < 1) {
            throw new InvalidInputException('Retry number must be at least one.');
        }

        // Clamp the first delay too, so even retry one respects a maximum below the base delay.
        $delay = min($this->baseDelayMs, $this->maxDelayMs);

        if ($delay === 0) {
            return 0;
        }

        for ($attempt = 1; $attempt < $retryNumber && $delay < $this->maxDelayMs; $attempt++) {
            // Saturate before doubling so a large retry number cannot overflow an integer.
            if ($delay > intdiv($this->maxDelayMs, 2)) {
                return $this->maxDelayMs;
            }

            $delay *= 2;
        }

        return $delay;
    }
}
