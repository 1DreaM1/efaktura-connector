<?php

namespace DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Transport\Execution;

use DreaM1\EfakturaConnector\Core\Exceptions\ProviderApiException;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Transport\HttpExchangeRequest;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Transport\HttpExchangeResult;

/**
 * Receives completed HTTP exchanges and failures for application-level logging.
 *
 * @api
 */
interface HttpRequestExecutionLoggerInterface
{
    /**
     * Records a response after the executor receives it.
     *
     * @param  HttpExchangeRequest $request  HTTP request associated with the execution event.
     * @param  HttpExchangeResult  $exchange HTTP exchange returned by the transport.
     * @return void
     */
    public function recordResponse(HttpExchangeRequest $request, HttpExchangeResult $exchange): void;

    /**
     * Records a failed attempt and its elapsed start time.
     *
     * @param  HttpExchangeRequest  $request   HTTP request associated with the execution event.
     * @param  ProviderApiException $exception Exception raised by the wrapped operation.
     * @param  float                $startedAt Unix timestamp when the operation started.
     * @return void
     */
    public function recordFailure(HttpExchangeRequest $request, ProviderApiException $exception, float $startedAt): void;
}
