<?php

namespace DreaM1\EfakturaConnector\Core\Exceptions;

use DreaM1\EfakturaConnector\Core\Configuration\HttpResponseBodyLimit;

/** The provider response exceeded the configured transport memory limit. */
final class ProviderResponseTooLargeException extends ProviderApiException
{
    private HttpResponseBodyLimit $responseBodyLimit;

    /**
     * Creates an exception describing a response rejected by the body-size limit.
     *
     * @param HttpResponseBodyLimit $responseBodyLimit Configured response body size limit.
     * @param int|null              $statusCode        HTTP status received before the body exceeded the limit, if available.
     * @param array<string, string> $responseHeaders   Headers received before the body exceeded the limit.
     */
    public function __construct(
        HttpResponseBodyLimit $responseBodyLimit,
        ?int $statusCode = null,
        array $responseHeaders = []
    ) {
        $this->responseBodyLimit = $responseBodyLimit;
        parent::__construct(
            'API response body exceeded the configured maximum of ' . $this->responseBodyLimit->bytes() . ' bytes.',
            $statusCode,
            $responseHeaders
        );
    }

    /** Returns the configured maximum response body size. */
    public function responseBodyLimit(): HttpResponseBodyLimit
    {
        return $this->responseBodyLimit;
    }
}
