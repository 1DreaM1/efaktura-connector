<?php

namespace DreaM1\EfakturaConnector\Infrastructure\EFaktura\Http;

use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Http\Protocol\HttpHeaders;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Http\Protocol\HttpMethod;

/** @internal Common immutable HTTP request data shared by API and transport requests. */
abstract class AbstractHttpRequest
{
    protected HttpMethod $method;
    protected HttpRequestTarget $target;
    protected HttpHeaders $headers;
    protected ?string $body;
    protected HttpRequestContext $context;
    protected string $acceptHeader;
    protected bool $retrySafe;

    /** Initializes the common request data used by immutable request builders. */
    protected function __construct(
        HttpMethod $method,
        HttpRequestTarget $target,
        HttpHeaders $headers,
        ?string $body,
        HttpRequestContext $context,
        string $acceptHeader,
        bool $retrySafe
    ) {
        $this->method       = $method;
        $this->target       = $target;
        $this->headers      = $headers;
        $this->body         = $body;
        $this->context      = $context;
        $this->acceptHeader = $acceptHeader;
        $this->retrySafe    = $retrySafe;
    }

    /** Returns the HTTP method as a validated value object. */
    public function httpMethod(): HttpMethod
    {
        return $this->method;
    }

    /** Returns the immutable resolved request target. */
    public function target(): HttpRequestTarget
    {
        return $this->target;
    }

    /** Returns the immutable ordered header collection. */
    public function headers(): HttpHeaders
    {
        return $this->headers;
    }

    /** Returns the raw request body, or null when the request has no body. */
    public function body(): ?string
    {
        return $this->body;
    }

    /** Returns the immutable generic request context. */
    public function requestContext(): HttpRequestContext
    {
        return $this->context;
    }

    /**
     * Returns request metadata reserved for logging and not sent to the provider.
     *
     * @return array<string, mixed>
     */
    public function context(): array
    {
        return $this->context->toArray();
    }

    /** Returns the accepted response media type. */
    public function acceptHeader(): string
    {
        return $this->acceptHeader;
    }

    /** Whether the HTTP request may be replayed after an ambiguous result. */
    public function isRetrySafe(): bool
    {
        return $this->retrySafe;
    }
}
