<?php

namespace DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Transport\Execution;

use DreaM1\EfakturaConnector\Core\CircuitBreaker\CircuitBreakerInterface;
use DreaM1\EfakturaConnector\Core\Exceptions\CircuitBreakerOpenException;
use DreaM1\EfakturaConnector\Core\Exceptions\ProviderConnectionException;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Transport\HttpExchangeRequest;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Transport\HttpExchangeResult;
use Throwable;

/**
 * Blocks calls while the circuit is open and records connection or server failures.
 *
 * Connection exceptions and HTTP 5xx responses count as failures. With a permit-aware breaker, a 429
 * releases a half-open probe without closing the circuit; other responses below 500 count as successful
 * provider exchanges. A basic breaker records a 429 as a success because it has no neutral outcome.
 */
final class CircuitBreakerHttpRequestExecutor implements HttpRequestExecutorInterface
{
    private HttpRequestExecutorInterface $executor;
    private CircuitBreakerInterface $circuitBreaker;

    /**
     * @param HttpRequestExecutorInterface $executor       Executor responsible for sending the HTTP request.
     * @param CircuitBreakerInterface      $circuitBreaker Circuit breaker used to guard provider requests.
     */
    public function __construct(
        HttpRequestExecutorInterface $executor,
        CircuitBreakerInterface $circuitBreaker
    ) {
        $this->executor       = $executor;
        $this->circuitBreaker = $circuitBreaker;
    }

    /**
     * Executes the prepared HTTP request.
     *
     * @param  HttpExchangeRequest $request Prepared HTTP request whose execution is guarded by the circuit breaker.
     * @return HttpExchangeResult
     * @throws Throwable           when the circuit is open or the wrapped executor or breaker implementation fails.
     */
    public function execute(HttpExchangeRequest $request): HttpExchangeResult
    {
        $permit = $this->circuitBreaker->acquireExecutionPermit();

        if ($permit === null) {
            throw new CircuitBreakerOpenException();
        }

        try {
            $result = $this->executor->execute($request);
        } catch (ProviderConnectionException $exception) {
            $this->circuitBreaker->recordFailureForPermit($permit);

            throw $exception;
        } catch (Throwable $exception) {
            // Local or unexpected errors do not prove provider instability; release a half-open probe as neutral.
            $this->circuitBreaker->recordNeutralForPermit($permit);

            throw $exception;
        }

        // Rate limiting is neutral; server failures count against provider health.
        if ($result->isTooManyRequests()) {
            $this->circuitBreaker->recordNeutralForPermit($permit);
        } elseif ($result->isServerError()) {
            $this->circuitBreaker->recordFailureForPermit($permit);
        } else {
            $this->circuitBreaker->recordSuccessForPermit($permit);
        }

        return $result;
    }
}
