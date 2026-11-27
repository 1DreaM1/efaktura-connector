<?php

namespace DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Transport\Execution;

use DreaM1\EfakturaConnector\Core\Exceptions\ProviderApiException;
use DreaM1\EfakturaConnector\Core\Exceptions\RequestCoordinationException;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Transport\HttpExchangeRequest;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Transport\HttpExchangeResult;
use DreaM1\EfakturaConnector\Support\Exceptions\InvalidInputException;
use DreaM1\EfakturaConnector\Support\Exceptions\InvariantViolationException;
use Throwable;

/** Decorates an executor with logging while keeping logger failures from changing its result. */
final class LoggingHttpRequestExecutor implements HttpRequestExecutorInterface
{
    private HttpRequestExecutorInterface $executor;
    private HttpRequestExecutionLoggerInterface $logger;

    /**
     * @param HttpRequestExecutorInterface        $executor Executor responsible for sending the HTTP request.
     * @param HttpRequestExecutionLoggerInterface $logger   Logger used to record sanitized connector diagnostics.
     */
    public function __construct(
        HttpRequestExecutorInterface $executor,
        HttpRequestExecutionLoggerInterface $logger
    ) {
        $this->executor = $executor;
        $this->logger   = $logger;
    }

    /**
     * Executes the prepared HTTP request.
     *
     * @param  HttpExchangeRequest          $request Prepared HTTP request to execute and log.
     * @return HttpExchangeResult
     * @throws ProviderApiException         when the wrapped executor fails
     * @throws RequestCoordinationException when local request coordination fails
     * @throws InvalidInputException        when the wrapped executor rejects the request path
     * @throws InvariantViolationException  when the wrapped executor has no configured provider base URL
     */
    public function execute(HttpExchangeRequest $request): HttpExchangeResult
    {
        // Capture before delegation so a failed attempt can be logged with its original start time.
        $startedAt = microtime(true);

        try {
            $exchange = $this->executor->execute($request);
        } catch (ProviderApiException $exception) {
            $this->recordFailure($request, $exception, $startedAt);

            throw $exception;
        }

        $this->recordResponse($request, $exchange);

        return $exchange;
    }

    /** Keeps response logging failures from affecting a successful exchange. */
    private function recordResponse(HttpExchangeRequest $request, HttpExchangeResult $exchange): void
    {
        try {
            $this->logger->recordResponse($request, $exchange);
        } catch (Throwable $exception) {
            // Logging must not change the provider response.
        }
    }

    /** Keeps logging failures from replacing the original transport exception. */
    private function recordFailure(
        HttpExchangeRequest $request,
        ProviderApiException $exception,
        float $startedAt
    ): void {
        try {
            $this->logger->recordFailure($request, $exception, $startedAt);
        } catch (Throwable $loggingException) {
            // Logging must not replace the original transport exception.
        }
    }
}
