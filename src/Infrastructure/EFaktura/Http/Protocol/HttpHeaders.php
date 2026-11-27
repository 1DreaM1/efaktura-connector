<?php

namespace DreaM1\EfakturaConnector\Infrastructure\EFaktura\Http\Protocol;

use DreaM1\EfakturaConnector\Support\Exceptions\InvalidInputException;
use DreaM1\EfakturaConnector\Support\TypedArray;

/**
 * Immutable ordered collection of validated HTTP header lines.
 *
 * This class validates header syntax only; request-specific rules such as identity scoping and
 * idempotency-key uniqueness belong to the request builder.
 *
 *
 * @api
 */
final class HttpHeaders
{
    /** @var HttpHeaderLine[] */
    private array $headers;

    /**
     * Initializes the collection from validated header lines.
     *
     * @param HttpHeaderLine[] $headers
     */
    private function __construct(array $headers)
    {
        $this->headers = $headers;
    }

    /** Creates an empty header collection. */
    public static function create(): self
    {
        return new self([]);
    }

    /**
     * Creates a collection from validated header lines.
     *
     * @param  HttpHeaderLine[]      $headers
     * @return self
     * @throws InvalidInputException when an item is not a HttpHeaderLine.
     */
    public static function fromLines(array $headers): self
    {
        return new self(TypedArray::valuesOfType(
            $headers,
            HttpHeaderLine::class,
            'HTTP headers must be HttpHeaderLine instances.'
        ));
    }

    /**
     * Returns a copy with this header set, replacing all values with the same name.
     * The replacement occupies the first matching position, or is appended when absent.
     *
     * @throws InvalidInputException when the header value contains invalid control characters.
     */
    public function withHeader(HttpHeaderName $name, string $value): self
    {
        $replacement = HttpHeaderLine::fromParts($name, $value);
        $headers     = [];
        $replaced    = false;

        foreach ($this->headers as $header) {
            if (! $header->headerName()->equals($name)) {
                $headers[] = $header;

                continue;
            }

            if (! $replaced) {
                $headers[] = $replacement;
                $replaced  = true;
            }
        }

        if (! $replaced) {
            $headers[] = $replacement;
        }

        return new self($headers);
    }

    /**
     * Returns a copy with an additional header line appended.
     *
     * @throws InvalidInputException when the header value contains invalid control characters.
     */
    public function withAddedHeader(HttpHeaderName $name, string $value): self
    {
        $headers   = $this->headers;
        $headers[] = HttpHeaderLine::fromParts($name, $value);

        return new self($headers);
    }

    /** Returns a copy without any values for this name, using case-insensitive matching. */
    public function withoutHeader(HttpHeaderName $name): self
    {
        $headers = [];

        foreach ($this->headers as $header) {
            if (! $header->headerName()->equals($name)) {
                $headers[] = $header;
            }
        }

        if (count($headers) === count($this->headers)) {
            return $this;
        }

        return new self($headers);
    }

    /**
     * Returns all header lines with this name, preserving their original order.
     *
     * @param  HttpHeaderName   $name Header name to match, using case-insensitive comparison.
     * @return HttpHeaderLine[]
     */
    public function allNamed(HttpHeaderName $name): array
    {
        $matches = [];

        foreach ($this->headers as $header) {
            if ($header->headerName()->equals($name)) {
                $matches[] = $header;
            }
        }

        return $matches;
    }

    /** Checks for a header using HTTP's case-insensitive name comparison. */
    public function hasHeader(HttpHeaderName $name): bool
    {
        foreach ($this->headers as $header) {
            if ($header->headerName()->equals($name)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Returns the ordered header lines.
     *
     * @return HttpHeaderLine[]
     */
    public function all(): array
    {
        return $this->headers;
    }
}
