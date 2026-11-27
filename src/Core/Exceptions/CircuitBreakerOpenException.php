<?php

namespace DreaM1\EfakturaConnector\Core\Exceptions;

/**
 * A request was rejected locally because the circuit breaker is open; it was not sent to the provider.
 * This exception is intentionally extensible.
 */
class CircuitBreakerOpenException extends ProviderConnectionException
{
    public function __construct()
    {
        parent::__construct('Circuit breaker open — provider temporarily unavailable.');
    }

    /** The breaker rejected the request locally, so nothing was sent. */
    public function requestMayHaveReachedProvider(): bool
    {
        return false;
    }
}
