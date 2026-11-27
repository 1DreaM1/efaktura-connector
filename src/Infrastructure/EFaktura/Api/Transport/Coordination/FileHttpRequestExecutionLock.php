<?php

namespace DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Transport\Coordination;

use DreaM1\EfakturaConnector\Core\Exceptions\RequestCoordinationException;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Transport\HttpExchangeResult;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Coordination\FileLock;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Coordination\LockStorageException;
use DreaM1\EfakturaConnector\Support\Exceptions\InvalidInputException;
use RuntimeException;
use Throwable;

/** Coordinates execution by provider rate-limit scope for one application and operating-system user. */
final class FileHttpRequestExecutionLock implements ScopedHttpRequestExecutionLockInterface
{
    /** Prefix used to create an application- and user-specific lock directory. */
    private const DIRECTORY_PREFIX = 'efaktura-connector-http-';

    private FileLock $fileLock;

    /**
     * Creates a file-backed lock scoped to an application and operating-system user.
     *
     * @param  string|null                  $applicationDirectory Application root used to isolate independent apps
     * @param  string|null                  $temporaryDirectory   Existing private or system temporary directory
     * @throws RequestCoordinationException when the lock directory cannot be initialized
     */
    public function __construct(?string $applicationDirectory = null, ?string $temporaryDirectory = null)
    {
        try {
            $this->fileLock = new FileLock(
                $applicationDirectory,
                $temporaryDirectory,
                self::DIRECTORY_PREFIX
            );
        } catch (LockStorageException $exception) {
            throw new RequestCoordinationException($exception);
        }
    }

    /**
     * Runs an operation while holding the lock for the supplied provider scope.
     *
     * @param  string                         $scopeKey Stable, non-empty provider scope key used to coordinate equivalent requests.
     * @param  callable(): HttpExchangeResult $callback Callback invoked when the operation completes or state changes.
     * @return HttpExchangeResult
     * @throws RequestCoordinationException   when the local file lock cannot be acquired
     * @throws InvalidInputException          when the scope key is blank
     * @throws Throwable                      when the guarded callback fails
     */
    public function synchronizedFor(string $scopeKey, callable $callback): HttpExchangeResult
    {
        if (trim($scopeKey) === '') {
            throw new InvalidInputException('The HTTP execution lock scope key must not be empty.');
        }

        try {
            $lock = $this->fileLock->acquire($scopeKey);
        } catch (LockStorageException $exception) {
            throw new RequestCoordinationException($exception);
        }

        if ($lock === null) {
            throw new RequestCoordinationException();
        }

        try {
            return $callback();
        } finally {
            $lock->release();
        }
    }
}
