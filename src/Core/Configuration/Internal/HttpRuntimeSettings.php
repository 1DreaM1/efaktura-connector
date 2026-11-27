<?php

namespace DreaM1\EfakturaConnector\Core\Configuration\Internal;

use DreaM1\EfakturaConnector\Core\Configuration\HttpRequestBodyLimit;
use DreaM1\EfakturaConnector\Core\Configuration\HttpResponseBodyLimit;

/** @internal Immutable group of HTTP runtime timeouts and body limits. */
final class HttpRuntimeSettings
{
    private int $connectTimeoutSeconds;
    private int $requestTimeoutSeconds;
    private HttpResponseBodyLimit $responseBodyLimit;
    private HttpRequestBodyLimit $requestBodyLimit;

    /**
     * Groups the validated HTTP timeout and body-size settings.
     *
     * @param int                   $connectTimeoutSeconds Maximum time to establish a connection, in seconds.
     * @param int                   $requestTimeoutSeconds Maximum time to complete a request, in seconds.
     * @param HttpResponseBodyLimit $responseBodyLimit     Maximum response body size accepted.
     * @param HttpRequestBodyLimit  $requestBodyLimit      Maximum encoded request body size accepted.
     */
    public function __construct(
        int $connectTimeoutSeconds,
        int $requestTimeoutSeconds,
        HttpResponseBodyLimit $responseBodyLimit,
        HttpRequestBodyLimit $requestBodyLimit
    ) {
        $this->connectTimeoutSeconds = $connectTimeoutSeconds;
        $this->requestTimeoutSeconds = $requestTimeoutSeconds;
        $this->responseBodyLimit     = $responseBodyLimit;
        $this->requestBodyLimit      = $requestBodyLimit;
    }

    /** Returns the connection timeout in seconds. */
    public function connectTimeoutSeconds(): int
    {
        return $this->connectTimeoutSeconds;
    }

    /** Returns the full request timeout in seconds. */
    public function requestTimeoutSeconds(): int
    {
        return $this->requestTimeoutSeconds;
    }

    /** Returns the maximum accepted response body size. */
    public function responseBodyLimit(): HttpResponseBodyLimit
    {
        return $this->responseBodyLimit;
    }

    /** Returns the maximum encoded request body size accepted. */
    public function requestBodyLimit(): HttpRequestBodyLimit
    {
        return $this->requestBodyLimit;
    }
}
