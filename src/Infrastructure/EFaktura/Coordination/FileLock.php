<?php

namespace DreaM1\EfakturaConnector\Infrastructure\EFaktura\Coordination;

use DreaM1\EfakturaConnector\Infrastructure\ResourcePath;
use DreaM1\EfakturaConnector\Support\HashAlgorithm;
use Throwable;

/** @internal Secure process lock primitive shared by file-backed coordination adapters. */
final class FileLock
{
    /** Minimum interval between lock attempts, in microseconds. */
    private const MINIMUM_RETRY_INTERVAL_MICROSECONDS = 1000;
    /** Delay between attempts to acquire a lock held by another process, in microseconds. */
    private const RETRY_INTERVAL_MICROSECONDS = 100000;
    /** Number of microseconds in one second. */
    private const MICROSECONDS_PER_SECOND = 1000000;

    private string $lockDirectory;
    private ?int $userId;
    private SecureLockFileStorage $fileStorage;

    /**
     * Creates the private lock directory for one application and runtime identity.
     *
     * @param  string|null          $applicationDirectory Application root used to isolate independent apps
     * @param  string|null          $temporaryDirectory   Existing private or system temporary directory
     * @param  string               $directoryPrefix      Prefix that isolates this lock family's files
     * @throws LockStorageException when the application or temporary directory is unavailable.
     */
    public function __construct(
        ?string $applicationDirectory,
        ?string $temporaryDirectory,
        string $directoryPrefix
    ) {
        $applicationDirectory = $applicationDirectory ?? getcwd();
        $temporaryDirectory   = $temporaryDirectory ?? sys_get_temp_dir();

        $canonicalApplicationDirectory = is_string($applicationDirectory)
            ? realpath($applicationDirectory)
            : false;

        $canonicalTemporaryDirectory = realpath($temporaryDirectory);

        if ($canonicalApplicationDirectory === false || ! is_dir($canonicalApplicationDirectory)) {
            throw new LockStorageException('The file lock application directory must exist.');
        }

        if (
            $canonicalTemporaryDirectory === false
            || ! is_dir($canonicalTemporaryDirectory)
            || ! is_writable($canonicalTemporaryDirectory)
        ) {
            throw new LockStorageException('The file lock temporary directory must be writable.');
        }

        $this->fileStorage = new SecureLockFileStorage();
        $this->userId      = $this->fileStorage->currentUserId();
        $runtimeIdentity   = $this->runtimeIdentity();
        // Keep each application's and operating-system user's lock files in a separate, opaque namespace.
        $scope               = hash(HashAlgorithm::SHA_256, $canonicalApplicationDirectory . "\0" . $runtimeIdentity);
        $this->lockDirectory = ResourcePath::join($canonicalTemporaryDirectory, $directoryPrefix . $scope);
    }

    /**
     * Acquires a lock for a scope and returns null when the optional wait limit expires.
     *
     * With a null wait limit, acquisition blocks until the lock becomes available.
     *
     * @param  string    $scopeKey    Stable identity hashed before it is written to a path
     * @param  int|null  $waitSeconds Maximum wait, or null to wait without a limit
     * @throws Throwable when secure lock-file validation or lock acquisition fails.
     */
    public function acquire(string $scopeKey, ?int $waitSeconds = null): ?FileLockResource
    {
        if (! function_exists('flock')) {
            throw new LockStorageException('The file lock requires flock().');
        }

        $lockFilePath = $this->lockFilePath($scopeKey);
        $this->prepareLockDirectory();
        $this->fileStorage->assertNotSymbolicLink($lockFilePath, 'path');

        $handle = LockFilesystem::fopen($lockFilePath, 'c');

        if (! is_resource($handle)) {
            throw new LockStorageException('The file lock could not open its lock file.');
        }

        try {
            // flock coordinates workers only if the opened handle still matches the secured path entry.
            $this->fileStorage->secureAndValidateLockFile($handle, $lockFilePath);

            if (! $this->acquireHandle($handle, $waitSeconds)) {
                fclose($handle);

                return null;
            }

            return new FileLockResource($handle);
        } catch (Throwable $exception) {
            if (is_resource($handle)) {
                fclose($handle);
            }

            throw $exception;
        }
    }

    private function lockFilePath(string $scopeKey): string
    {
        return ResourcePath::join($this->lockDirectory, hash(HashAlgorithm::SHA_256, $scopeKey) . '.lock');
    }

    /** @param resource $handle */
    private function acquireHandle($handle, ?int $waitSeconds): bool
    {
        if ($waitSeconds === null) {
            return LockFilesystem::flock($handle, LOCK_EX);
        }

        $deadline = microtime(true) + max(0, $waitSeconds);

        // Poll a non-blocking lock so a caller-supplied wait limit can be enforced.
        while (true) {
            if (LockFilesystem::flock($handle, LOCK_EX | LOCK_NB)) {
                return true;
            }

            $remaining = $deadline - microtime(true);

            if ($remaining <= 0) {
                return false;
            }

            usleep((int) min(
                self::RETRY_INTERVAL_MICROSECONDS,
                max(self::MINIMUM_RETRY_INTERVAL_MICROSECONDS, $remaining * self::MICROSECONDS_PER_SECOND)
            ));
        }
    }

    private function prepareLockDirectory(): void
    {
        $canonicalDirectory = $this->fileStorage->preparePrivateDirectory(
            $this->lockDirectory,
            false,
            true
        );

        if ($canonicalDirectory !== $this->lockDirectory) {
            throw new LockStorageException('The file lock directory is not accessible.');
        }
    }

    private function runtimeIdentity(): string
    {
        // Prefer an OS user ID; environment variables are fallbacks on platforms without that facility.
        if ($this->userId !== null) {
            return 'uid:' . $this->userId;
        }

        if ($this->fileStorage->isWindows()) {
            $username = getenv('USERNAME');

            if (is_string($username) && $username !== '') {
                $domain  = getenv('USERDOMAIN') ?: '';
                $profile = getenv('USERPROFILE') ?: '';

                return 'windows:' . strtolower($domain . '\\' . $username . "\0" . $profile);
            }
        }

        foreach (['USERNAME', 'USER', 'LOGNAME'] as $environmentKey) {
            $identity = getenv($environmentKey);

            if (is_string($identity) && $identity !== '') {
                return strtolower($environmentKey . ':' . $identity);
            }
        }

        return 'php-user:' . get_current_user();
    }
}
