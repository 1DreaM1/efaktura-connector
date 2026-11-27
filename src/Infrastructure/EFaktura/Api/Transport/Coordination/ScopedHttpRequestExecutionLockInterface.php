<?php

namespace DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Transport\Coordination;

use DreaM1\EfakturaConnector\Core\Exceptions\RequestCoordinationException;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Transport\HttpExchangeResult;
use DreaM1\EfakturaConnector\Support\Exceptions\InvalidInputException;
use Throwable;

/**
 * Serializes HTTP execution independently for each provider rate-limit scope.
 *
 * @api
 */
interface ScopedHttpRequestExecutionLockInterface
{
    /**
     * Runs one HTTP execution while holding the lock for the supplied non-sensitive scope key.
     *
     * @param  string                         $scopeKey Stable provider rate-limit identity; implementations should not expose it in paths
     * @param  callable(): HttpExchangeResult $callback Callback invoked when the operation completes or state changes.
     * @return HttpExchangeResult
     * @throws RequestCoordinationException   when local request
     *                                        coordination cannot be established
     * @throws InvalidInputException          when the scope key is blank
     * @throws Throwable                      when the callback fails
     * @throws InvalidInputException          when the scope key is blank
     */
    public function synchronizedFor(string $scopeKey, callable $callback): HttpExchangeResult;
}
