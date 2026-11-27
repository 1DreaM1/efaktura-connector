<?php

namespace DreaM1\EfakturaConnector\Core\Exceptions;

use Throwable;

/**
 * The request did not complete: cURL or another network failure, or retries exhausted.
 * Unless created with beforeSending(), the request may or may not have reached the provider; see
 * requestMayHaveReachedProvider().
 * This exception is intentionally extensible.
 */
class ProviderConnectionException extends ProviderApiException
{
    private bool $beforeSending = false;

    /**
     * Creates a failure that happened before any bytes were sent, such as a cURL handle that could not be set up.
     *
     * @param string         $message  Technical description of the local transport failure.
     * @param Throwable|null $previous Previous exception that caused this failure, when available.
     */
    public static function beforeSending(string $message, ?Throwable $previous = null): self
    {
        $exception                = new self($message, null, [], $previous);
        $exception->beforeSending = true;

        return $exception;
    }

    /** False when the failure happened before sending; otherwise the request may have arrived. */
    public function requestMayHaveReachedProvider(): bool
    {
        return ! $this->beforeSending;
    }
}
