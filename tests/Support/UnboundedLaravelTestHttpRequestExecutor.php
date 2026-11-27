<?php

namespace DreaM1\EfakturaConnector\Tests\Support;

use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Transport\Execution\HttpRequestExecutorInterface;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Transport\HttpExchangeRequest;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Transport\HttpExchangeResult;

/** Test executor with no declared maximum runtime. */
final class UnboundedLaravelTestHttpRequestExecutor implements HttpRequestExecutorInterface
{
    /** {@inheritdoc} */
    public function execute(HttpExchangeRequest $request): HttpExchangeResult
    {
        return new HttpExchangeResult(200, '{}', [], microtime(true));
    }
}
