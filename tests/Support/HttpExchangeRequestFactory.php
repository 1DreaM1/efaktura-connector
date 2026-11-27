<?php

namespace DreaM1\EfakturaConnector\Tests\Support;

use DreaM1\EfakturaConnector\Core\Configuration\ProviderBaseUrl;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Transport\HttpExchangeRequest;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Http\HttpRequestContext;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Http\HttpRequestTarget;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Http\Protocol\HttpHeaderLine;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Http\Protocol\HttpHeaders;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Http\Protocol\HttpMethod;

/** Builds transport test requests from concise raw paths and header fixtures. */
final class HttpExchangeRequestFactory
{
    /**
     * @param HttpHeaderLine[]     $headers
     * @param array<string, mixed> $context
     */
    public static function create(
        HttpMethod $method,
        string $path,
        array $headers = [],
        ?string $body = null,
        array $context = [],
        ?string $acceptHeader = null,
        ?ProviderBaseUrl $baseUrl = null,
        ?bool $retrySafe = null,
        ?string $backpressureScopeKey = null
    ): HttpExchangeRequest {
        $request = new HttpExchangeRequest(
            $method,
            HttpRequestTarget::fromResolvedTarget($path),
            HttpHeaders::fromLines($headers),
            $body,
            HttpRequestContext::fromArray($context),
            $acceptHeader,
            $retrySafe
        );

        if ($baseUrl !== null) {
            $request = $request->withBaseUrl($baseUrl);
        }

        if ($backpressureScopeKey !== null) {
            $request = $request->withBackpressureScopeKey($backpressureScopeKey);
        }

        return $request;
    }
}
