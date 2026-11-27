<?php

namespace DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Transport;

use DreaM1\EfakturaConnector\Core\Configuration\ProviderBaseUrl;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Http\AbstractHttpRequest;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Http\HttpRequestContext;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Http\HttpRequestTarget;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Http\Protocol\HttpHeaderLine;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Http\Protocol\HttpHeaderName;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Http\Protocol\HttpHeaders;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Http\Protocol\HttpMethod;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Http\Protocol\MediaType;
use DreaM1\EfakturaConnector\Support\Exceptions\InvalidInputException;

/**
 * Immutable request passed through the HTTP transport and executor decorators.
 *
 * @api
 */
final class HttpExchangeRequest extends AbstractHttpRequest
{
    private ?ProviderBaseUrl $baseUrl     = null;
    private ?string $backpressureScopeKey = null;

    /**
     * Creates a transport request with headers, body and optional retry context.
     *
     * @param  HttpMethod            $method       HTTP method for the provider request.
     * @param  HttpRequestTarget     $target       Resolved path and query relative to the provider base URL.
     * @param  HttpHeaders           $headers      Validated immutable HTTP header collection.
     * @param  string|null           $body         Raw HTTP request body, or null when the request has no body.
     * @param  HttpRequestContext    $context      Logging metadata that is not sent to the provider.
     * @param  string|null           $acceptHeader HTTP Accept header describing response formats accepted by the caller.
     * @param  bool|null             $retrySafe    Whether a retry decorator may replay this operation; defaults to GET only.
     * @throws InvalidInputException when the Accept value is invalid.
     *
     * The provider base URL and the backpressure scope are attached later by the transport, through
     * withBaseUrl() and withBackpressureScopeKey().
     */
    public function __construct(
        HttpMethod $method,
        HttpRequestTarget $target,
        HttpHeaders $headers,
        ?string $body,
        HttpRequestContext $context,
        ?string $acceptHeader = null,
        ?bool $retrySafe = null
    ) {
        $resolvedAcceptHeader = $acceptHeader ?? MediaType::APPLICATION_JSON;
        HttpHeaderLine::assertValid(HttpHeaderName::ACCEPT, $resolvedAcceptHeader);

        parent::__construct(
            $method,
            $target,
            $headers,
            $body,
            $context,
            $resolvedAcceptHeader,
            $retrySafe ?? $method->isGet()
        );
    }

    /**
     * Returns the request path relative to the configured provider base URL.
     *
     * @return string
     */
    public function path(): string
    {
        return $this->target->toString();
    }

    /** Checks for a header using HTTP's case-insensitive name comparison. */
    public function hasHeader(HttpHeaderName $name): bool
    {
        return $this->headers->hasHeader($name);
    }

    /**
     * Returns a copy with this header set, replacing existing values case-insensitively.
     *
     * @throws InvalidInputException when the header value contains invalid control characters.
     */
    public function withHeader(HttpHeaderName $name, string $value): self
    {
        return $this->withHeaders($this->headers->withHeader($name, $value));
    }

    /** Returns a copy without any values for this header name, using case-insensitive matching. */
    public function withoutHeader(HttpHeaderName $name): self
    {
        $headers = $this->headers->withoutHeader($name);

        return $headers === $this->headers ? $this : $this->withHeaders($headers);
    }

    /**
     * Returns a copy bound to the configured provider base URL.
     *
     * @param  ProviderBaseUrl $baseUrl Configured provider base URL.
     * @return self
     */
    public function withBaseUrl(ProviderBaseUrl $baseUrl): self
    {
        $request          = clone $this;
        $request->baseUrl = $baseUrl;

        return $request;
    }

    /**
     * @internal Returns a copy scoped to a stable provider client identity for backpressure coordination.
     * @throws InvalidInputException when the scope key is blank.
     */
    public function withBackpressureScopeKey(string $scopeKey): self
    {
        if (trim($scopeKey) === '') {
            throw new InvalidInputException('Backpressure scope key must not be empty.');
        }

        $request                       = clone $this;
        $request->backpressureScopeKey = $scopeKey;

        return $request;
    }

    /** @internal Returns the stable configured client identity, or null for requests built outside Transport. */
    public function backpressureScopeKey(): ?string
    {
        return $this->backpressureScopeKey;
    }

    /** Returns the configured provider base URL, or null when no base URL has been attached. */
    public function baseUrlOrNull(): ?ProviderBaseUrl
    {
        return $this->baseUrl;
    }

    /** Returns a copy that uses the supplied immutable header collection. */
    private function withHeaders(HttpHeaders $headers): self
    {
        $request          = clone $this;
        $request->headers = $headers;

        return $request;
    }
}
