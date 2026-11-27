<?php

namespace DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Transport\Execution;

use DreaM1\EfakturaConnector\Core\Exceptions\CircuitBreakerOpenException;
use DreaM1\EfakturaConnector\Core\Exceptions\ProviderApiException;
use DreaM1\EfakturaConnector\Core\Exceptions\RequestDeferredException;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Transport\HttpExchangeRequest;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Transport\HttpExchangeResult;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Metrics\ConnectorMetricsInterface;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Metrics\SafeConnectorMetrics;
use Throwable;

/**
 * Reports requests rejected locally, before any HTTP attempt was made.
 *
 * Logging and metrics sit below the retry layer and observe only real attempts. A request refused by an
 * open circuit breaker or deferred by provider backpressure never reaches them, so this outermost layer
 * records those rejections once per operation: a metric with the `circuit_open` or `deferred` outcome and a
 * logger failure event. Sink failures never replace the original exception.
 */
final class RejectionObservingHttpRequestExecutor implements HttpRequestExecutorInterface
{
    private HttpRequestExecutorInterface $executor;
    private ?HttpRequestExecutionLoggerInterface $logger;
    private ?ConnectorMetricsInterface $metrics;

    /**
     * Wraps an executor and reports its local rejections.
     *
     * @param HttpRequestExecutorInterface             $executor Executor stack whose rejections are reported.
     * @param HttpRequestExecutionLoggerInterface|null $logger   Optional logger receiving a failure event.
     * @param ConnectorMetricsInterface|null           $metrics  Optional metrics sink; failures are isolated.
     */
    public function __construct(
        HttpRequestExecutorInterface $executor,
        ?HttpRequestExecutionLoggerInterface $logger,
        ?ConnectorMetricsInterface $metrics
    ) {
        $this->executor = $executor;
        $this->logger   = $logger;
        $this->metrics  = $metrics;
    }

    /**
     * Executes the request and reports it when it is rejected locally.
     *
     * @param  HttpExchangeRequest  $request Prepared HTTP request.
     * @return HttpExchangeResult   Unmodified exchange returned by the wrapped executor.
     * @throws ProviderApiException when the wrapped executor fails or rejects the request
     */
    public function execute(HttpExchangeRequest $request): HttpExchangeResult
    {
        $startedAt = microtime(true);

        try {
            return $this->executor->execute($request);
        } catch (CircuitBreakerOpenException $exception) {
            $this->report($request, $exception, $startedAt, ConnectorMetricsInterface::API_CIRCUIT_OPEN);

            throw $exception;
        } catch (RequestDeferredException $exception) {
            $this->report($request, $exception, $startedAt, ConnectorMetricsInterface::API_DEFERRED);

            throw $exception;
        }
    }

    /** Reports one rejection to the metrics sink and the logger without letting either throw. */
    private function report(
        HttpExchangeRequest $request,
        ProviderApiException $exception,
        float $startedAt,
        string $outcome
    ): void {
        if ($this->metrics !== null) {
            SafeConnectorMetrics::apiRequest(
                $this->metrics,
                $request->httpMethod()->value(),
                null,
                max(0.0, microtime(true) - $startedAt),
                $outcome
            );
        }

        if ($this->logger === null) {
            return;
        }

        try {
            $this->logger->recordFailure($request, $exception, $startedAt);
        } catch (Throwable $loggingException) {
            // Logging must not replace the original rejection.
        }
    }
}
