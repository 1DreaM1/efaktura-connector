<?php

namespace DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Transport;

use DreaM1\EfakturaConnector\Support\HttpStatusCode;
use DreaM1\EfakturaConnector\Support\NonBlankString;

/**
 * Raw HTTP status, body, selected response headers and request start time.
 *
 * @api
 */
final class HttpExchangeResult
{
    private int $rawStatusCode;
    private ?HttpStatusCode $statusCode;
    private string $body;
    /** @var array<string, string> Selected response headers keyed by lowercase name. */
    private array $headers;
    private float $startedAt;

    /**
     * @param array<string, string> $headers   Selected response headers keyed by lowercase name
     * @param int                   $status    Raw transport status; values outside 100 through 599 are retained but invalid.
     * @param string                $body      Raw HTTP response body.
     * @param float                 $startedAt Unix timestamp when the operation started.
     */
    public function __construct(int $status, string $body, array $headers, float $startedAt)
    {
        $this->rawStatusCode = $status;
        $this->statusCode    = HttpStatusCode::tryFromInt($status);
        $this->body          = $body;
        $this->headers       = $headers;
        $this->startedAt     = $startedAt;
    }

    /** Returns a valid HTTP status code, or null when the received value is invalid. */
    public function httpStatusCode(): ?HttpStatusCode
    {
        return $this->statusCode;
    }

    /** Returns the exact numeric status value received from the transport. */
    public function rawStatusCode(): int
    {
        return $this->rawStatusCode;
    }

    /**
     * Returns the body.
     *
     * @return string
     */
    public function body(): string
    {
        return $this->body;
    }

    /**
     * Returns the headers.
     *
     * @return array<string, string>
     */
    public function headers(): array
    {
        return $this->headers;
    }

    /**
     * Returns the time at which the HTTP request started.
     *
     * @return float
     */
    public function startedAt(): float
    {
        return $this->startedAt;
    }

    /**
     * Whether the response is an empty HTTP 204 result.
     *
     * @return bool
     */
    public function isWithoutContent(): bool
    {
        return $this->statusCode !== null
            && $this->statusCode->isNoContent()
            && ! NonBlankString::isNotBlank($this->body);
    }

    /** Whether the received response is a valid 2xx response. */
    public function isSuccessful(): bool
    {
        return $this->statusCode !== null && $this->statusCode->isSuccessful();
    }

    /** Invalid status values are processed as server errors as RFC 9110 recommends. */
    public function isServerError(): bool
    {
        return $this->statusCode === null || $this->statusCode->isServerError();
    }

    /** Whether the response is a valid HTTP 429 response. */
    public function isTooManyRequests(): bool
    {
        return $this->statusCode !== null && $this->statusCode->isTooManyRequests();
    }
}
