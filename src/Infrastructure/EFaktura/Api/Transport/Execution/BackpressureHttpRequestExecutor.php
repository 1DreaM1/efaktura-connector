<?php

namespace DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Transport\Execution;

use DreaM1\EfakturaConnector\Core\Exceptions\RequestCoordinationException;
use DreaM1\EfakturaConnector\Core\Exceptions\RequestDeferredException;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Transport\Coordination\FileHttpRequestExecutionLock;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Transport\Coordination\HttpRequestBackpressureInterface;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Transport\Coordination\HttpRequestBackpressureKey;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Transport\Coordination\ScopedHttpRequestExecutionLockInterface;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Transport\HttpExchangeRequest;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Transport\HttpExchangeResult;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Clock\ClockInterface;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Clock\SystemClock;
use Throwable;

/**
 * Serializes HTTP work within the provider rate-limit scope and rejects requests during a stored cooldown.
 * Cooldowns are written by RateLimitRecordingHttpRequestExecutor placed below this decorator.
 */
final class BackpressureHttpRequestExecutor implements HttpRequestExecutorInterface
{
    private HttpRequestExecutorInterface $executor;
    private HttpRequestBackpressureInterface $backpressure;
    private ?ScopedHttpRequestExecutionLockInterface $executionLock;
    private ClockInterface $clock;

    /**
     * Wraps an executor with shared provider cooldown checks and optional request serialization.
     *
     * @param  HttpRequestExecutorInterface                 $executor             Executor that performs the HTTP exchange.
     * @param  HttpRequestBackpressureInterface             $backpressure         Store for provider rate-limit deadlines.
     * @param  ScopedHttpRequestExecutionLockInterface|null $executionLock        Optional cross-process lock.
     * @param  bool                                         $executionLockEnabled Whether to serialize checks and requests under the provider scope.
     * @param  ClockInterface|null                          $clock                Time source for cooldown checks; defaults to the system clock.
     * @throws RequestCoordinationException                 when the default file lock cannot be initialized
     */
    public function __construct(
        HttpRequestExecutorInterface $executor,
        HttpRequestBackpressureInterface $backpressure,
        ?ScopedHttpRequestExecutionLockInterface $executionLock = null,
        bool $executionLockEnabled = true,
        ?ClockInterface $clock = null
    ) {
        $this->executor     = $executor;
        $this->backpressure = $backpressure;

        // Prefer an injectable cross-process lock, falling back to the private file-lock adapter
        // when serialization is enabled and the host has not supplied its own implementation.
        $this->executionLock = $executionLockEnabled
            ? ($executionLock ?? new FileHttpRequestExecutionLock())
            : null;

        $this->clock = $clock ?? new SystemClock();
    }

    /**
     * Executes the request unless a stored provider cooldown is still active.
     *
     * @param  HttpExchangeRequest      $request Request to execute.
     * @return HttpExchangeResult       Provider response and transport metadata.
     * @throws RequestDeferredException when a stored provider cooldown defers the request
     * @throws Throwable                when the wrapped executor, backpressure store, or coordination lock fails.
     */
    public function execute(HttpExchangeRequest $request): HttpExchangeResult
    {
        $execute = function () use ($request): HttpExchangeResult {
            // Check the shared deadline before entering the inner retry/circuit/HTTP stack.
            $retryAt = $this->backpressure->blockedUntil($request);

            if ($retryAt !== null && $retryAt->isAfter($this->clock->now())) {
                throw new RequestDeferredException($retryAt);
            }

            return $this->executor->execute($request);
        };

        // Keep the cooldown check and network work in one critical section. A
        // waiting request then checks the deadline again after the previous request completes.
        return $this->executionLock === null
            ? $execute()
            : $this->executionLock->synchronizedFor(HttpRequestBackpressureKey::forRequest($request), $execute);
    }
}
