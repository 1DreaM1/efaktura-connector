<?php

namespace DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Transport\Execution;

use DreaM1\EfakturaConnector\Support\Exceptions\InvariantViolationException;

/**
 * @internal Owns one cURL easy handle: a resource on PHP 7.4, a CurlHandle object on PHP 8 and later.
 *
 * Keeps the runtime-dependent handle type, and its cleanup, out of the executors.
 */
final class CurlEasyHandle
{
    /** @var mixed Native cURL handle (resource or CurlHandle, depending on the PHP version), or null once closed. */
    private $handle;

    /** @param mixed $handle Native cURL handle returned by curl_init(). */
    private function __construct($handle)
    {
        $this->handle = $handle;
    }

    /**
     * Initializes a handle, optionally preset with a URL.
     *
     * @return self|null The handle, or null when cURL could not be initialized.
     */
    public static function open(?string $url = null): ?self
    {
        $handle = $url === null ? curl_init() : curl_init($url);

        return $handle === false ? null : new self($handle);
    }

    /**
     * Applies options to the handle.
     *
     * @param  array<int, mixed> $options CURLOPT_* values keyed by option constant.
     * @return bool              Whether every option was accepted.
     */
    public function setOptions(array $options): bool
    {
        return curl_setopt_array($this->native(), $options);
    }

    /** Returns the last cURL error message, or an empty string. */
    public function error(): string
    {
        return curl_error($this->native());
    }

    /**
     * Performs the transfer.
     *
     * @return bool|string False on failure; otherwise the transfer result (the body goes to the write callback).
     */
    public function execute()
    {
        return curl_exec($this->native());
    }

    /** Returns the HTTP status code of the last transfer, or 0 when none was received. */
    public function httpStatus(): int
    {
        return (int) curl_getinfo($this->native(), CURLINFO_HTTP_CODE);
    }

    /** Clears request-specific options while keeping libcurl's connection and DNS caches. */
    public function reset(): void
    {
        curl_reset($this->native());
    }

    /** Releases the handle. Calling it again has no effect. */
    public function close(): void
    {
        $handle       = $this->handle;
        $this->handle = null;

        // PHP 7.4 represents cURL handles as resources; PHP 8+ frees CurlHandle objects automatically.
        if (is_resource($handle)) {
            curl_close($handle);
        }
    }

    /**
     * @return mixed
     * @throws InvariantViolationException when the handle was already closed.
     */
    private function native()
    {
        if ($this->handle === null) {
            throw new InvariantViolationException('The cURL handle has been closed.');
        }

        return $this->handle;
    }
}
