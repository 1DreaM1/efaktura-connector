<?php

namespace DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Transport\Coordination;

use DreaM1\EfakturaConnector\Core\Retry\RetryDeadline;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Transport\HttpExchangeRequest;

/**
 * Stores provider cooldowns so concurrent callers can defer requests before sending them.
 *
 * @api
 */
interface HttpRequestBackpressureInterface
{
    /**
     * Returns the provider cooldown deadline, or null when the request is currently allowed.
     *
     * @param  HttpExchangeRequest $request Request used to derive the provider and credential scope.
     * @return RetryDeadline|null  Time after which the request may proceed.
     */
    public function blockedUntil(HttpExchangeRequest $request): ?RetryDeadline;

    /**
     * Extends the cooldown for requests sharing this provider identity.
     *
     * @param HttpExchangeRequest $request Request used to derive the shared cooldown scope.
     * @param RetryDeadline       $retryAt Earliest time at which requests may proceed.
     */
    public function deferUntil(HttpExchangeRequest $request, RetryDeadline $retryAt): void;
}
