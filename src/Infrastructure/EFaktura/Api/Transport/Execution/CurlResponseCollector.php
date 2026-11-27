<?php

namespace DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Transport\Execution;

use DreaM1\EfakturaConnector\Core\Configuration\HttpResponseBodyLimit;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Http\Protocol\HttpHeaderLine;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Http\Protocol\HttpHeaderName;

/**
 * @internal Collects one cURL response through the write and header callbacks.
 *
 * The body is capped while chunks arrive, so an oversized body never enters memory, and only the headers
 * that response handling and retries need are retained.
 */
final class CurlResponseCollector
{
    /** Pattern for a valid HTTP status-line prefix in response headers. */
    private const HTTP_STATUS_LINE_PATTERN = '/\AHTTP\/\S+\s+\d{3}(?:\s|\z)/i';

    private HttpResponseBodyLimit $limit;
    private string $body   = '';
    private int $bodyBytes = 0;
    private bool $tooLarge = false;
    /** @var array<string, string> */
    private array $headers = [];

    /** @param HttpResponseBodyLimit $limit Maximum number of body bytes that may be collected. */
    public function __construct(HttpResponseBodyLimit $limit)
    {
        $this->limit = $limit;
    }

    /** Returns the cURL write callback that appends body chunks until the response limit would be exceeded. */
    public function bodyCallback(): callable
    {
        return function ($handle, string $chunk): int {
            $chunkBytes = strlen($chunk);

            // Enforce the response limit while receiving chunks, before an oversized body enters memory.
            if ($this->limit->wouldExceed($this->bodyBytes, $chunkBytes)) {
                $this->tooLarge = true;

                return 0;
            }

            $this->body .= $chunk;
            $this->bodyBytes += $chunkBytes;

            return $chunkBytes;
        };
    }

    /** Returns the cURL header callback that keeps only the headers needed by response handling and retries. */
    public function headerCallback(): callable
    {
        return function ($handle, string $header): int {
            if (preg_match(self::HTTP_STATUS_LINE_PATTERN, $header) === 1) {
                // A new status line starts a new response, for example after an interim 100 Continue.
                $this->headers = [];
            }

            // cURL includes the HTTP line terminator; remove it before strict parsing.
            $headerLine = HttpHeaderLine::parse(rtrim($header, "\r\n"));

            if ($headerLine !== null && self::isRelevant($headerLine)) {
                $this->headers[$headerLine->name()] = $headerLine->value();
            }

            return strlen($header);
        };
    }

    /** Returns the collected body. */
    public function body(): string
    {
        return $this->body;
    }

    /** Whether the body was cut off because it would have exceeded the response limit. */
    public function wasTooLarge(): bool
    {
        return $this->tooLarge;
    }

    /**
     * Returns the retained headers of the latest response.
     *
     * @return array<string, string>
     */
    public function headers(): array
    {
        return $this->headers;
    }

    private static function isRelevant(HttpHeaderLine $headerLine): bool
    {
        return $headerLine->headerName()->isNamed(HttpHeaderName::CONTENT_TYPE)
            || $headerLine->headerName()->isNamed(HttpHeaderName::RETRY_AFTER)
            || $headerLine->headerName()->isNamed(HttpHeaderName::X_CORRELATION_ID)
            || $headerLine->headerName()->isNamed(HttpHeaderName::X_REQUEST_ID);
    }
}
