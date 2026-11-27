<?php

namespace DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Transport\Execution;

use DreaM1\EfakturaConnector\Core\Exceptions\ProviderConnectionException;
use DreaM1\EfakturaConnector\Core\Exceptions\ProviderResponseTooLargeException;
use DreaM1\EfakturaConnector\Core\Exceptions\RequestCoordinationException;
use DreaM1\EfakturaConnector\Core\Exceptions\RequestDeferredException;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Transport\HttpExchangeRequest;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Transport\HttpExchangeResult;
use DreaM1\EfakturaConnector\Support\Exceptions\InvalidInputException;
use DreaM1\EfakturaConnector\Support\Exceptions\InvariantViolationException;

/**
 * Executes one prepared HTTP request; decorators can add retries, logging or circuit breaking.
 *
 * @api
 */
interface HttpRequestExecutorInterface
{
    /**
     * Executes the prepared request and returns the raw HTTP exchange.
     *
     * @param  HttpExchangeRequest               $request Prepared HTTP request to execute.
     * @return HttpExchangeResult
     * @throws ProviderConnectionException       when no response is received
     * @throws ProviderResponseTooLargeException when the response body exceeds the configured limit
     * @throws RequestDeferredException          when backpressure defers the request
     *                                           before sending
     * @throws RequestCoordinationException      when local request
     *                                           coordination cannot be established
     * @throws InvalidInputException             when the request path does not form a safe provider URL
     * @throws InvariantViolationException       when the request has no configured provider base URL
     */
    public function execute(HttpExchangeRequest $request): HttpExchangeResult;
}
