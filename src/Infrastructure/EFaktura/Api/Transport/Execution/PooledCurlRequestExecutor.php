<?php

namespace DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Transport\Execution;

use DreaM1\EfakturaConnector\Core\Exceptions\ProviderConnectionException;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Transport\HttpExchangeRequest;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Transport\HttpExchangeResult;
use DreaM1\EfakturaConnector\Support\Exceptions\InvariantViolationException;
use Throwable;

/** Reuses a cURL easy handle and its connection cache across sequential requests in a long-lived worker. */
final class PooledCurlRequestExecutor extends CurlRequestExecutor
{
    private ?CurlEasyHandle $handle = null;
    private bool $executing         = false;

    /** Releases the retained cURL handle when the executor is discarded. */
    public function __destruct()
    {
        $this->discardHandle();
    }

    /**
     * Executes the request and keeps the easy handle alive so libcurl can reuse eligible connections.
     *
     * @param  HttpExchangeRequest $request Prepared HTTP request to execute using the retained cURL handle.
     * @return HttpExchangeResult
     * @throws Throwable           when re-entrant use is attempted or URL resolution, cURL execution, or response handling fails.
     */
    public function execute(HttpExchangeRequest $request): HttpExchangeResult
    {
        // One easy handle has mutable request options, so this guard rejects re-entrant use instead
        // of allowing two requests to overwrite each other's cURL configuration.
        if ($this->executing) {
            throw new InvariantViolationException('A pooled cURL executor cannot execute concurrent requests.');
        }

        $url = ProviderRequestUrlResolver::resolve(
            $request->baseUrlOrNull(),
            $request->path()
        );

        $this->executing = true;
        try {
            if ($this->handle === null) {
                $this->handle = CurlEasyHandle::open();

                if ($this->handle === null) {
                    throw ProviderConnectionException::beforeSending('Failed to initialize cURL.');
                }
            }

            // Reset request-specific options while preserving libcurl's connection and DNS caches.
            $this->handle->reset();

            return $this->executeWithHandle($request, $this->handle, false, $url);
        } catch (Throwable $exception) {
            $this->discardHandle();

            throw $exception;
        } finally {
            $this->executing = false;
        }
    }

    /**
     * Closes the retained handle. The executor can create a fresh handle on a later request.
     *
     * @throws InvariantViolationException when called while a request is executing.
     */
    public function close(): void
    {
        if ($this->executing) {
            throw new InvariantViolationException('A pooled cURL executor cannot be closed during a request.');
        }

        $this->discardHandle();
    }

    private function discardHandle(): void
    {
        if ($this->handle === null) {
            return;
        }

        $handle       = $this->handle;
        $this->handle = null;
        $handle->close();
    }
}
