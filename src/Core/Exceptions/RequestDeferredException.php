<?php

namespace DreaM1\EfakturaConnector\Core\Exceptions;

use DreaM1\EfakturaConnector\Core\Retry\RetryDeadline;

/** A request was held by local backpressure and was not sent to the provider. */
final class RequestDeferredException extends ProviderApiException
{
    private RetryDeadline $retryAt;

    /**
     * Creates an exception with the earliest retry time supplied by local backpressure.
     *
     * @param RetryDeadline $retryAt Earliest time at which the request may be retried.
     */
    public function __construct(RetryDeadline $retryAt)
    {
        parent::__construct('Request deferred by provider backpressure.');
        $this->retryAt = $retryAt;
    }

    /** The request was held locally and never sent. */
    public function requestMayHaveReachedProvider(): bool
    {
        return false;
    }

    /** Returns the earliest time at which the host may retry the request. */
    public function retryAt(): RetryDeadline
    {
        return $this->retryAt;
    }
}
