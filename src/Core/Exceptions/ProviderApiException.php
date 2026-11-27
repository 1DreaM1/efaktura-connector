<?php

namespace DreaM1\EfakturaConnector\Core\Exceptions;

use DreaM1\EfakturaConnector\Core\Retry\RetryAfter;
use DreaM1\EfakturaConnector\Core\Retry\RetryDeadline;
use DreaM1\EfakturaConnector\Support\HttpStatusCode;
use Throwable;

/**
 * A SAPI or Peppol Gateway operation failed or was deferred:
 *
 *   ProviderConnectionException       the request did not complete (network failure); it may have arrived
 *   ProviderErrorResponseException    the provider answered with a non-2xx status (response kept as received)
 *   InvalidProviderResponseException  the provider answered 2xx, but the body is not what the API documents
 *   TokenRequestException             the OAuth token could not be obtained, so the request itself was not sent
 *   RequestDeferredException          local backpressure held the request before it was sent
 *
 * Use requestMayHaveReachedProvider() to tell failures that certainly sent nothing from those that may have.
 */
abstract class ProviderApiException extends EFakturaException
{
    private ?HttpStatusCode $httpStatusCode;
    private ?int $rawStatusCode;
    /** @var array<string, string> */
    private array $responseHeaders;

    /**
     * Creates a new ProviderApiException value.
     * @param string                $message         Message or diagnostic text associated with the result.
     * @param int|null              $statusCode      HTTP status code received from the provider, when available.
     * @param array<string, string> $responseHeaders HTTP response headers retained from the provider.
     * @param Throwable|null        $previous        Previous exception that caused this failure, when available.
     */
    public function __construct(
        string $message = '',
        ?int $statusCode = null,
        array $responseHeaders = [],
        ?Throwable $previous = null
    ) {
        parent::__construct($message, $previous);
        $this->rawStatusCode  = $statusCode;
        $this->httpStatusCode = $this->rawStatusCode !== null
            ? HttpStatusCode::tryFromInt($this->rawStatusCode)
            : null;
        $this->responseHeaders = $responseHeaders;
    }

    /** Returns a valid typed HTTP status code, or null when there was no valid status. */
    public function httpStatusCode(): ?HttpStatusCode
    {
        return $this->httpStatusCode;
    }

    /** Returns the exact numeric value received from the transport, including invalid values. */
    public function rawStatusCode(): ?int
    {
        return $this->rawStatusCode;
    }

    /**
     * Returns response headers retained by the transport for the failure, if any.
     * @return array<string, string>
     */
    public function responseHeaders(): array
    {
        return $this->responseHeaders;
    }

    /**
     * Tells whether the request could have reached the provider before this failure.
     *
     * False means the request was certainly not sent (deferred, circuit breaker open, OAuth token unavailable,
     * or the transport failed before sending), so repeating it cannot duplicate a side effect. True is the
     * conservative answer for everything else: an unsent document and a lost response look the same, so a
     * document submission must be repeated with the same idempotency key and payload.
     */
    public function requestMayHaveReachedProvider(): bool
    {
        return true;
    }

    /** Returns a usable Retry-After deadline for host-side delayed retry scheduling. */
    public function retryAt(): ?RetryDeadline
    {
        return RetryAfter::deadline($this->responseHeaders);
    }
}
