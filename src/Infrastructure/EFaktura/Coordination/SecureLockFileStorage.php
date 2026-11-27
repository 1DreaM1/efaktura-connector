<?php

namespace DreaM1\EfakturaConnector\Infrastructure\EFaktura\Coordination;

use DreaM1\EfakturaConnector\Infrastructure\ResourcePath;
use RuntimeException;

/** @internal Creates private lock directories and validates lock files shared by coordination adapters. */
final class SecureLockFileStorage
{
    /** POSIX mode mask selecting the file type bits. */
    private const FILE_TYPE_MASK = 0170000;
    /** POSIX mode mask selecting permission bits. */
    private const PERMISSION_BITS_MASK = 0777;
    /** POSIX mask selecting write permission for group or other users. */
    private const GROUP_AND_OTHER_WRITE_MASK = 0022;
    /** POSIX permissions used for private lock directories. */
    private const DIRECTORY_MODE = 0700;
    /** POSIX permissions used for private lock files. */
    private const FILE_MODE = 0600;
    /** POSIX file-type bits identifying a regular file. */
    private const REGULAR_FILE_TYPE = 0100000;
    /** POSIX file-type bits identifying a directory. */
    private const DIRECTORY_TYPE = 0040000;
    /** POSIX sticky-bit mask checked for a shared temporary directory. */
    private const STICKY_BIT = 01000;

    private ?int $userId;

    /** Captures the effective operating-system user ID when it can be determined. */
    public function __construct()
    {
        $this->userId = self::detectCurrentUserId();
    }

    /** Returns the effective user ID when the platform exposes it. */
    public function currentUserId(): ?int
    {
        return $this->userId;
    }

    /** Returns whether the current PHP runtime is running on Windows. */
    public function isWindows(): bool
    {
        return DIRECTORY_SEPARATOR === '\\';
    }

    /**
     * Creates or validates a private lock directory and returns its canonical path.
     *
     * @param  string               $path                    Lock directory to prepare.
     * @param  bool                 $createParentDirectories Whether missing parent directories may be created recursively.
     * @param  bool                 $repairPermissions       Whether an existing directory may be changed to mode 0700.
     * @throws LockStorageException when the directory cannot be secured
     */
    public function preparePrivateDirectory(
        string $path,
        bool $createParentDirectories,
        bool $repairPermissions
    ): string {
        $parentPath = dirname($path);
        $parent     = $this->resolveParentDirectory($parentPath, $createParentDirectories);

        if (! $this->isWindows()) {
            $this->assertSafeParentDirectories($parent);
        }

        // Resolve the parent first, then inspect the requested directory as one final path entry.
        $path = ResourcePath::join($parent, basename($path));
        $this->assertNotSymbolicLink($path, 'directory');
        $this->createDirectoryIfMissing($path);
        $stat = $this->assertSafeDirectory($path);

        if (! $this->isWindows()) {
            $this->assertOwnedByCurrentUser($path);
            $this->secureDirectoryPermissions($path, $stat, $repairPermissions);
        }

        return $this->canonicalWritableDirectory($path);
    }

    /**
     * Rejects symbolic links at a lock file or directory path.
     *
     * @param  string               $path        Path to inspect.
     * @param  string               $description Lock resource name used in the exception message.
     * @throws LockStorageException when the path is a symbolic link
     */
    public function assertNotSymbolicLink(string $path, string $description): void
    {
        clearstatcache(true, $path);

        if (is_link($path)) {
            throw new LockStorageException('The file lock ' . $description . ' must not be a symbolic link.');
        }
    }

    /**
     * Validates the opened handle and secures the lock file against replacement and hard links.
     *
     * @param  resource             $handle Open lock file handle.
     * @param  string               $path   Filesystem path associated with the open handle.
     * @throws LockStorageException when the handle or path is unsafe
     */
    public function secureAndValidateLockFile($handle, string $path): void
    {
        $this->assertNotSymbolicLink($path, 'path');
        clearstatcache(true, $path);
        $opened   = LockFilesystem::fstat($handle);
        $pathStat = LockFilesystem::lstat($path);

        if (! is_array($opened) || ! is_array($pathStat) || ! is_file($path)) {
            throw new LockStorageException('The file lock path must refer to one regular file.');
        }

        if (! $this->isWindows()) {
            $this->assertSameRegularFile($opened, $pathStat);
            $this->assertOwnedByCurrentUser($path);

            $permissions = LockFilesystem::fileperms($path);

            if ($permissions === false) {
                throw new LockStorageException('The file lock permissions are unavailable.');
            }

            if (($permissions & self::PERMISSION_BITS_MASK) !== self::FILE_MODE && ! LockFilesystem::chmod($path, self::FILE_MODE)) {
                throw new LockStorageException('The file lock permissions could not be secured.');
            }

            // Recheck the path after chmod so a concurrent replacement cannot pass validation.
            $this->assertNotSymbolicLink($path, 'path');
            clearstatcache(true, $path);
            $opened      = LockFilesystem::fstat($handle);
            $pathStat    = LockFilesystem::lstat($path);
            $permissions = LockFilesystem::fileperms($path);

            if (
                ! is_array($opened)
                || ! is_array($pathStat)
                || $permissions === false
                || ($permissions & self::PERMISSION_BITS_MASK) !== self::FILE_MODE
            ) {
                throw new LockStorageException('The file lock is not private.');
            }

            $this->assertSameRegularFile($opened, $pathStat);
        }
    }

    private function resolveParentDirectory(string $path, bool $createParentDirectories): string
    {
        if (! is_dir($path)) {
            if (
                ! $createParentDirectories
                || (! LockFilesystem::mkdir($path, self::DIRECTORY_MODE, true) && ! is_dir($path))
            ) {
                throw new LockStorageException('The file lock parent directory is unavailable.');
            }
        }

        $parent = realpath($path);

        if ($parent === false || ! is_dir($parent)) {
            throw new LockStorageException('The file lock parent directory is unavailable.');
        }

        return $parent;
    }

    private function createDirectoryIfMissing(string $path): void
    {
        if (file_exists($path)) {
            return;
        }

        $created = LockFilesystem::mkdir($path, self::DIRECTORY_MODE);
        clearstatcache(true, $path);

        if (! $created && ! file_exists($path)) {
            throw new LockStorageException('The file lock directory could not be created.');
        }
    }

    /** @return array{mode: int, dev: int, ino: int} */
    private function assertSafeDirectory(string $path): array
    {
        clearstatcache(true, $path);
        $stat = LockFilesystem::lstat($path);

        if (
            is_link($path)
            || ! is_array($stat)
            || (! $this->isWindows() && (($stat['mode'] & self::FILE_TYPE_MASK) !== self::DIRECTORY_TYPE))
            || ! is_dir($path)
        ) {
            throw new LockStorageException('The file lock path is not a safe directory.');
        }

        return $stat;
    }

    /** @param array{mode: int, dev: int, ino: int} $stat Directory identity checked before permission changes. */
    private function secureDirectoryPermissions(string $path, array $stat, bool $repairPermissions): void
    {
        clearstatcache(true, $path);
        $permissions = LockFilesystem::fileperms($path);

        if ($permissions === false) {
            throw new LockStorageException('The file lock directory permissions are unavailable.');
        }

        if (($permissions & self::PERMISSION_BITS_MASK) === self::DIRECTORY_MODE) {
            return;
        }

        if (! $repairPermissions || ! LockFilesystem::chmod($path, self::DIRECTORY_MODE)) {
            throw new LockStorageException('The file lock directory is not private.');
        }

        // chmod follows the path; ensure it still points to the same directory checked above.
        $this->assertNotSymbolicLink($path, 'directory');
        clearstatcache(true, $path);
        $securedStat = LockFilesystem::lstat($path);
        $permissions = LockFilesystem::fileperms($path);

        if (
            ! is_array($securedStat)
            || $securedStat['dev'] !== $stat['dev']
            || $securedStat['ino'] !== $stat['ino']
            || (($securedStat['mode'] & self::FILE_TYPE_MASK) !== self::DIRECTORY_TYPE)
            || $permissions === false
            || ($permissions & self::PERMISSION_BITS_MASK) !== self::DIRECTORY_MODE
        ) {
            throw new LockStorageException('The file lock directory is not private.');
        }
    }

    private function canonicalWritableDirectory(string $path): string
    {
        $canonicalPath = realpath($path);

        if ($canonicalPath === false || $canonicalPath !== $path || ! is_writable($canonicalPath)) {
            throw new LockStorageException('The file lock directory is unavailable.');
        }

        return $canonicalPath;
    }

    /**
     * Confirms that the opened handle and path identify the same single-link regular file.
     *
     * @param array{mode: int, dev: int, ino: int, nlink?: int} $opened   Result returned by fstat().
     * @param array{mode: int, dev: int, ino: int, nlink?: int} $pathStat Result returned by lstat().
     */
    private function assertSameRegularFile(array $opened, array $pathStat): void
    {
        // Compare the open handle with the path entry to catch replacement; a single link avoids aliases.
        if (
            (($opened['mode'] & self::FILE_TYPE_MASK) !== self::REGULAR_FILE_TYPE)
            || (($pathStat['mode'] & self::FILE_TYPE_MASK) !== self::REGULAR_FILE_TYPE)
            || $opened['dev'] !== $pathStat['dev']
            || $opened['ino'] !== $pathStat['ino']
            || (isset($opened['nlink']) && $opened['nlink'] !== 1)
        ) {
            throw new LockStorageException('The file lock path must refer to one regular file.');
        }
    }

    private function assertOwnedByCurrentUser(string $path): void
    {
        if ($this->userId === null) {
            return;
        }

        clearstatcache(true, $path);
        $owner = LockFilesystem::fileowner($path);

        if ($owner === false || $owner !== $this->userId) {
            throw new LockStorageException('The file lock path must be owned by the current user.');
        }
    }

    /** Rejects writable parent directories that allow other users to replace lock path entries. */
    private function assertSafeParentDirectories(string $path): void
    {
        while (true) {
            clearstatcache(true, $path);
            $stat = LockFilesystem::lstat($path);

            if (! is_array($stat) || (($stat['mode'] & self::FILE_TYPE_MASK) !== self::DIRECTORY_TYPE)) {
                throw new LockStorageException('The file lock parent directory is not safe.');
            }

            if (($stat['mode'] & self::GROUP_AND_OTHER_WRITE_MASK) !== 0) {
                $ownerIsTrusted = isset($stat['uid'])
                    && ($stat['uid'] === 0 || ($this->userId !== null && $stat['uid'] === $this->userId));

                if (($stat['mode'] & self::STICKY_BIT) === 0 || ! $ownerIsTrusted) {
                    throw new LockStorageException('The file lock parent directory is writable by other users.');
                }
            }

            $parent = dirname($path);

            if ($parent === $path) {
                return;
            }

            $path = $parent;
        }
    }

    private static function detectCurrentUserId(): ?int
    {
        if (function_exists('posix_geteuid')) {
            return posix_geteuid();
        }

        if (DIRECTORY_SEPARATOR !== '\\' && is_readable('/proc/self/status')) {
            $status = LockFilesystem::fileGetContents('/proc/self/status');

            if (is_string($status) && preg_match('/^Uid:\s+(\d+)/m', $status, $matches) === 1) {
                return (int) $matches[1];
            }
        }

        return null;
    }
}
