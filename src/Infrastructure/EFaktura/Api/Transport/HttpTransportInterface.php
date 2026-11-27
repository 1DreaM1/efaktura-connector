<?php

namespace DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Transport;

use DreaM1\EfakturaConnector\Core\Exceptions\ConfigurationException;
use DreaM1\EfakturaConnector\Core\Exceptions\ProviderApiException;
use DreaM1\EfakturaConnector\Core\Exceptions\RequestCoordinationException;
use DreaM1\EfakturaConnector\Core\Exceptions\RequestEncodingException;
use DreaM1\EfakturaConnector\Support\Exceptions\InvalidInputException;

/**
 * Sends requests through the base URL and HTTP executor configured by the host.
 *
 * @api
 */
interface HttpTransportInterface
{
    /**
     * Sends an HTTP request and returns the unparsed exchange.
     *
     * @param  HttpExchangeRequest          $request Prepared HTTP request to send through the configured executor.
     * @return HttpExchangeResult
     * @throws ProviderApiException         when the provider request fails or is deferred
     * @throws RequestEncodingException     when authentication request data cannot be encoded
     * @throws RequestCoordinationException when local request
     *                                      coordination cannot be established
     * @throws ConfigurationException       when required authentication settings are missing
     * @throws InvalidInputException        when the request path or authentication headers are invalid
     */
    public function send(HttpExchangeRequest $request): HttpExchangeResult;
}
