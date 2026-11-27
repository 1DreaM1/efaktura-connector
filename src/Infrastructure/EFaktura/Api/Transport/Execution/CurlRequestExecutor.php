<?php

namespace DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Transport\Execution;

use DreaM1\EfakturaConnector\Core\Configuration\ConnectorRuntimeConfiguration;
use DreaM1\EfakturaConnector\Core\Configuration\HttpResponseBodyLimit;
use DreaM1\EfakturaConnector\Core\Exceptions\ProviderConnectionException;
use DreaM1\EfakturaConnector\Core\Exceptions\ProviderResponseTooLargeException;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Transport\HttpExchangeRequest;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Transport\HttpExchangeResult;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Http\Protocol\HttpHeaderLine;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Http\Protocol\HttpHeaderName;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Http\Protocol\MediaType;
use DreaM1\EfakturaConnector\Support\Exceptions\InvalidInputException;
use DreaM1\EfakturaConnector\Support\Exceptions\InvariantViolationException;

/**
 * Sends HTTPS requests with cURL and returns the raw response without interpreting its body.
 *
 * TLS peer and host verification stay enabled, redirects are disabled, and the URL is restricted
 * to HTTPS so credentials or document data are not forwarded to a redirect target. Only response
 * headers needed for retry and request correlation are retained; the provider body remains raw for
 * the API layer to validate and map.
 */
class CurlRequestExecutor implements HttpRequestExecutorInterface
{
    /** Default maximum time to establish an HTTPS connection, in seconds. */
    public const DEFAULT_CONNECT_TIMEOUT_SECONDS = ConnectorRuntimeConfiguration::DEFAULT_HTTP_CONNECT_TIMEOUT_SECONDS;
    /** Default maximum time for a complete HTTP request, in seconds. */
    public const DEFAULT_REQUEST_TIMEOUT_SECONDS = ConnectorRuntimeConfiguration::DEFAULT_HTTP_REQUEST_TIMEOUT_SECONDS;
    /** cURL value that enforces exact HTTPS peer hostname matching. */
    private const CURL_SSL_VERIFYHOST_STRICT = 2;
    /** Header line with an empty value; cURL then omits its automatic Expect: 100-continue header. */
    private const DISABLED_EXPECT_HEADER = 'Expect:';

    private int $connectTimeoutSeconds;
    private int $requestTimeoutSeconds;
    private HttpResponseBodyLimit $responseBodyLimit;

    /**
     * Configures connection, request and response-size limits for cURL transfers.
     *
     * @param  int                        $connectTimeoutSeconds Maximum time to establish an HTTPS connection, in seconds.
     * @param  int                        $requestTimeoutSeconds Maximum time for the complete HTTP request, in seconds.
     * @param  HttpResponseBodyLimit|null $responseBodyLimit     Maximum response body size retained in memory.
     * @throws InvalidInputException      when a timeout is nonpositive or the response limit is outside its supported range
     */
    public function __construct(
        int $connectTimeoutSeconds = self::DEFAULT_CONNECT_TIMEOUT_SECONDS,
        int $requestTimeoutSeconds = self::DEFAULT_REQUEST_TIMEOUT_SECONDS,
        ?HttpResponseBodyLimit $responseBodyLimit = null
    ) {
        if ($connectTimeoutSeconds < 1 || $requestTimeoutSeconds < 1) {
            throw new InvalidInputException('cURL timeouts must be positive.');
        }

        $this->connectTimeoutSeconds = $connectTimeoutSeconds;
        $this->requestTimeoutSeconds = $requestTimeoutSeconds;
        $this->responseBodyLimit     = $responseBodyLimit ?? HttpResponseBodyLimit::default();
    }

    /**
     * Executes the prepared HTTP request.
     *
     * @param  HttpExchangeRequest               $request Prepared request with provider base URL, path, headers and optional body.
     * @return HttpExchangeResult                Raw response body, selected headers and timing metadata.
     * @throws ProviderConnectionException       when cURL setup or transfer fails
     * @throws ProviderResponseTooLargeException when the response body exceeds the configured limit
     * @throws InvariantViolationException       when the request has no configured provider base URL
     * @throws InvalidInputException             when the request path does not form a safe provider URL
     */
    public function execute(HttpExchangeRequest $request): HttpExchangeResult
    {
        $url = ProviderRequestUrlResolver::resolve(
            $request->baseUrlOrNull(),
            $request->path()
        );

        $handle = CurlEasyHandle::open($url);

        if ($handle === null) {
            throw ProviderConnectionException::beforeSending('Failed to initialize cURL.');
        }

        return $this->executeWithHandle($request, $handle, true, $url);
    }

    /**
     * Executes one transfer on an initialized handle, optionally retaining the handle for reuse.
     *
     * @param  CurlEasyHandle                    $handle cURL easy handle to run the transfer on.
     * @throws ProviderConnectionException       when cURL cannot configure or complete the transfer.
     * @throws ProviderResponseTooLargeException when the response body exceeds the configured limit.
     */
    protected function executeWithHandle(
        HttpExchangeRequest $request,
        CurlEasyHandle $handle,
        bool $closeHandleAfterRequest,
        string $url
    ): HttpExchangeResult {
        $headers = self::requestHeaders($request);

        $startedAt = microtime(true);
        $collector = new CurlResponseCollector($this->responseBodyLimit);

        $options = [
            CURLOPT_URL            => $url,
            CURLOPT_CUSTOMREQUEST  => $request->httpMethod()->value(),
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_CONNECTTIMEOUT => $this->connectTimeoutSeconds,
            CURLOPT_TIMEOUT        => $this->requestTimeoutSeconds,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => self::CURL_SSL_VERIFYHOST_STRICT,
            // Disabled because CURLOPT_PROTOCOLS is deprecated; URLs are validated as HTTPS and redirects are disabled.
            // CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
            CURLOPT_WRITEFUNCTION  => $collector->bodyCallback(),
            CURLOPT_HEADERFUNCTION => $collector->headerCallback(),
        ];

        if ($request->body() !== null) {
            $options[CURLOPT_POSTFIELDS] = $request->body();
        }

        try {
            if (! $handle->setOptions($options)) {
                $error = $handle->error();
                throw ProviderConnectionException::beforeSending(
                    $error === '' ? 'Failed to configure cURL request.' : $error
                );
            }

            $success = $handle->execute();
            $error   = $success === false ? $handle->error() : null;
            $status  = $handle->httpStatus();
        } finally {
            if ($closeHandleAfterRequest) {
                $handle->close();
            }
        }

        if ($collector->wasTooLarge()) {
            // Keep this distinct from a connection failure; retry policy must not replay oversized responses.
            throw new ProviderResponseTooLargeException(
                $this->responseBodyLimit,
                $status,
                $collector->headers()
            );
        }

        if ($success === false) {
            // A failed cURL transfer is a connection exception; HTTP error statuses are returned below as exchanges.
            $message = is_string($error) && $error !== ''
                ? $error
                : 'cURL request failed without an error message.';
            throw new ProviderConnectionException($message, $status > 0 ? $status : null, $collector->headers());
        }

        // Keep HTTP status interpretation at the API boundary, where non-2xx responses become typed provider errors.
        return new HttpExchangeResult($status, $collector->body(), $collector->headers(), $startedAt);
    }

    /**
     * Builds one effective Accept and Content-Type header while preserving other caller headers.
     *
     * Requests with a body also disable cURL's automatic Expect: 100-continue handshake.
     *
     * @return string[]
     */
    private static function requestHeaders(HttpExchangeRequest $request): array
    {
        $acceptName      = strtolower(HttpHeaderName::ACCEPT);
        $contentTypeName = strtolower(HttpHeaderName::CONTENT_TYPE);

        $headers = [
            $acceptName => HttpHeaderLine::fromParts(
                HttpHeaderName::accept(),
                $request->acceptHeader()
            ),
            $contentTypeName => $request->body() !== null
                ? HttpHeaderLine::fromParts(
                    HttpHeaderName::contentType(),
                    MediaType::APPLICATION_JSON
                )
                : null,
        ];

        $additionalHeaders = [];

        foreach ($request->headers()->all() as $headerLine) {
            if (! array_key_exists($headerLine->name(), $headers)) {
                $additionalHeaders[] = $headerLine->toString();
                continue;
            }

            // Header names are normalized, and later caller values replace earlier defaults or duplicates.
            $headers[$headerLine->name()] = $headerLine;
        }

        $headers = array_values(array_filter($headers, static function (?HttpHeaderLine $header): bool {
            return $header instanceof HttpHeaderLine;
        }));

        $headers = array_map(static function (HttpHeaderLine $header): string {
            return $header->toString();
        }, $headers);

        $headers = array_merge($headers, $additionalHeaders);

        if ($request->body() !== null) {
            // Without this, cURL sends Expect: 100-continue for larger bodies and waits for the server
            // to confirm (or for a timeout) before uploading, which adds latency to document submission.
            $headers[] = self::DISABLED_EXPECT_HEADER;
        }

        return $headers;
    }
}
