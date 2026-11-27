<?php

namespace DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Transport\Execution;

use DreaM1\EfakturaConnector\Core\Exceptions\ProviderApiException;
use DreaM1\EfakturaConnector\Core\Exceptions\ProviderResponseTooLargeException;
use DreaM1\EfakturaConnector\Core\Exceptions\RequestCoordinationException;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Transport\HttpExchangeRequest;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Transport\HttpExchangeResult;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Metrics\ConnectorMetricsInterface;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Metrics\SafeConnectorMetrics;
use DreaM1\EfakturaConnector\Support\Exceptions\InvalidInputException;
use DreaM1\EfakturaConnector\Support\Exceptions\InvariantViolationException;
use DreaM1\EfakturaConnector\Support\HttpStatusCode;

/** Records one metric for every actual HTTP attempt while leaving the exchange untouched. */
final class MetricsHttpRequestExecutor implements HttpRequestExecutorInterface
{
    private HttpRequestExecutorInterface $executor;
    private ConnectorMetricsInterface $metrics;

    /**
     * Wraps an executor and records low-cardinality metrics for each attempt.
     *
     * @param HttpRequestExecutorInterface $executor Executor whose HTTP attempts should be measured.
     * @param ConnectorMetricsInterface    $metrics  Metrics sink; sink failures are isolated by SafeConnectorMetrics.
     */
    public function __construct(HttpRequestExecutorInterface $executor, ConnectorMetricsInterface $metrics)
    {
        $this->executor = $executor;
        $this->metrics  = $metrics;
    }

    /**
     * Executes one exchange and records its outcome and elapsed time, including thrown provider errors.
     *
     * @param  HttpExchangeRequest          $request Request to execute.
     * @return HttpExchangeResult           Unmodified exchange returned by the wrapped executor.
     * @throws ProviderApiException         when the wrapped executor fails
     * @throws RequestCoordinationException when local request coordination fails
     * @throws InvalidInputException        when the wrapped executor rejects the request path
     * @throws InvariantViolationException  when the wrapped executor has no configured provider base URL
     */
    public function execute(HttpExchangeRequest $request): HttpExchangeResult
    {
        // Measure only this wrapped attempt; the outer retry layer's backoff delay is not API time.
        $startedAt = microtime(true);

        try {
            $exchange = $this->executor->execute($request);
        } catch (ProviderApiException $exception) {
            $statusCode = $exception->rawStatusCode();
            SafeConnectorMetrics::apiRequest(
                $this->metrics,
                $request->httpMethod()->value(),
                $statusCode,
                max(0.0, microtime(true) - $startedAt),
                $this->outcomeForException($exception, $statusCode)
            );

            throw $exception;
        }

        SafeConnectorMetrics::apiRequest(
            $this->metrics,
            $request->httpMethod()->value(),
            $exchange->rawStatusCode(),
            max(0.0, microtime(true) - $startedAt),
            self::outcomeForExchange($exchange)
        );

        return $exchange;
    }

    /** Classifies transport exceptions before falling back to any retained provider status. */
    private function outcomeForException(ProviderApiException $exception, ?int $statusCode): string
    {
        // A response-size limit is a local transport rejection, even when an HTTP status was received.
        if ($exception instanceof ProviderResponseTooLargeException) {
            return ConnectorMetricsInterface::API_RESPONSE_TOO_LARGE;
        }

        // No status means no HTTP response was available to classify as a provider error.
        if ($statusCode === null) {
            return ConnectorMetricsInterface::API_CONNECTION_ERROR;
        }

        return self::outcomeForStatus($statusCode);
    }

    private static function outcomeForStatus(int $statusCode): string
    {
        $httpStatusCode = HttpStatusCode::tryFromInt($statusCode);

        if ($httpStatusCode === null) {
            // RFC 9110 recommends processing a received invalid status as a server error.
            return ConnectorMetricsInterface::API_SERVER_ERROR;
        }

        return self::outcomeForHttpStatus($httpStatusCode);
    }

    private static function outcomeForExchange(HttpExchangeResult $exchange): string
    {
        $statusCode = $exchange->httpStatusCode();

        return $statusCode === null
            ? ConnectorMetricsInterface::API_SERVER_ERROR
            : self::outcomeForHttpStatus($statusCode);
    }

    private static function outcomeForHttpStatus(HttpStatusCode $statusCode): string
    {
        if ($statusCode->isInformational()) {
            return ConnectorMetricsInterface::API_INFORMATIONAL;
        }

        if ($statusCode->isSuccessful()) {
            return ConnectorMetricsInterface::API_SUCCESS;
        }

        if ($statusCode->isRedirection()) {
            return ConnectorMetricsInterface::API_REDIRECTION;
        }

        if ($statusCode->isTooManyRequests()) {
            return ConnectorMetricsInterface::API_RATE_LIMITED;
        }

        if ($statusCode->isServerError()) {
            return ConnectorMetricsInterface::API_SERVER_ERROR;
        }

        return ConnectorMetricsInterface::API_CLIENT_ERROR;
    }
}
