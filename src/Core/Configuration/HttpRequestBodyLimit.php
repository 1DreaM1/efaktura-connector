<?php

namespace DreaM1\EfakturaConnector\Core\Configuration;

use DreaM1\EfakturaConnector\Support\Exceptions\InvalidInputException;

/** Maximum encoded HTTP request body size accepted by configured connector operations, in bytes. */
final class HttpRequestBodyLimit
{
    /** Default maximum encoded request body size, in bytes. */
    public const DEFAULT_BYTES = 100 * 1024 * 1024;
    /** Largest request body limit accepted through runtime configuration, in bytes. */
    public const MAX_BYTES = 1024 * 1024 * 1024;

    private int $bytes;

    private function __construct(int $bytes)
    {
        $this->bytes = $bytes;
    }

    /**
     * Creates a request body limit within the supported range.
     *
     * @param  int                   $bytes Maximum encoded request body size, in bytes.
     * @return self
     * @throws InvalidInputException when the limit is outside the supported range.
     */
    public static function fromBytes(int $bytes): self
    {
        if ($bytes < 1 || $bytes > self::MAX_BYTES) {
            throw new InvalidInputException(
                'HTTP request body limit must be between 1 and ' . self::MAX_BYTES . ' bytes.'
            );
        }

        return new self($bytes);
    }

    /** Returns the default request body limit. */
    public static function default(): self
    {
        return new self(self::DEFAULT_BYTES);
    }

    /** Returns the configured request body limit in bytes. */
    public function bytes(): int
    {
        return $this->bytes;
    }
}
