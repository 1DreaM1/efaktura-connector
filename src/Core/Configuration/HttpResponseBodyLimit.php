<?php

namespace DreaM1\EfakturaConnector\Core\Configuration;

use DreaM1\EfakturaConnector\Support\Exceptions\InvalidInputException;

/** Maximum response body size accepted by the HTTP transport, expressed in bytes. */
final class HttpResponseBodyLimit
{
    /** Default maximum response body size accepted by the HTTP transport, in bytes. */
    public const DEFAULT_BYTES = 16 * 1024 * 1024;
    /** Largest response body size configurable through the public runtime settings, in bytes. */
    public const MAX_BYTES = 128 * 1024 * 1024;

    private int $bytes;

    private function __construct(int $bytes)
    {
        $this->bytes = $bytes;
    }

    /**
     * Creates a response body limit within the supported range.
     *
     * @param  int                   $bytes Maximum response body size, in bytes.
     * @return self
     * @throws InvalidInputException when the limit is outside the supported range.
     */
    public static function fromBytes(int $bytes): self
    {
        if ($bytes < 1 || $bytes > self::MAX_BYTES) {
            throw new InvalidInputException(
                'HTTP response body limit must be between 1 and ' . self::MAX_BYTES . ' bytes.'
            );
        }

        return new self($bytes);
    }

    /** Returns the default response body limit. */
    public static function default(): self
    {
        return new self(self::DEFAULT_BYTES);
    }

    /** Returns the configured limit in bytes. */
    public function bytes(): int
    {
        return $this->bytes;
    }

    /**
     * Whether adding the next body chunk would exceed the configured limit.
     *
     * @param  int                   $receivedBytes  Number of response bytes already retained.
     * @param  int                   $nextChunkBytes Number of bytes in the next transport chunk.
     * @throws InvalidInputException when either byte count is negative.
     */
    public function wouldExceed(int $receivedBytes, int $nextChunkBytes): bool
    {
        if ($receivedBytes < 0 || $nextChunkBytes < 0) {
            throw new InvalidInputException('HTTP response byte counts must not be negative.');
        }

        if ($receivedBytes > $this->bytes) {
            return true;
        }

        // Compare with remaining capacity instead of adding lengths, which could overflow an integer.
        return $nextChunkBytes > $this->bytes - $receivedBytes;
    }

    /** Whether both objects represent the same response body limit. */
    public function equals(self $other): bool
    {
        return $this->bytes === $other->bytes;
    }
}
