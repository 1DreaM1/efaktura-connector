<?php

namespace DreaM1\EfakturaConnector\Infrastructure\EFaktura\Coordination;

/**
 * @internal The only place where lock-file code suppresses PHP warnings.
 *
 * Lock coordination probes the filesystem under races and restricted permissions: a file can vanish between two
 * calls, a directory can be unreadable, flock() can be unsupported. PHP reports each of these as a warning in
 * addition to the false return value. Callers always check that return value and turn a failure into a
 * LockStorageException (or a documented fallback), so the warning only adds noise or, under an error handler that
 * converts warnings, a different exception. Keeping the `@` here means no other code needs it.
 */
final class LockFilesystem
{
    private function __construct()
    {
    }

    /**
     * @return resource|false
     */
    public static function fopen(string $path, string $mode)
    {
        return @fopen($path, $mode);
    }

    /**
     * @param resource  $handle
     * @param int<0, 7> $operation
     */
    public static function flock($handle, int $operation): bool
    {
        return @flock($handle, $operation);
    }

    /** @param resource $handle */
    public static function fclose($handle): bool
    {
        return @fclose($handle);
    }

    /**
     * @param  resource                                                                                                                                                        $handle
     * @return array{dev: int, ino: int, mode: int, nlink: int, uid: int, gid: int, rdev: int, size: int, atime: int, mtime: int, ctime: int, blksize: int, blocks: int}|false
     */
    public static function fstat($handle)
    {
        return @fstat($handle);
    }

    /** @return array{dev: int, ino: int, mode: int, nlink: int, uid: int, gid: int, rdev: int, size: int, atime: int, mtime: int, ctime: int, blksize: int, blocks: int}|false */
    public static function lstat(string $path)
    {
        return @lstat($path);
    }

    /** @return int|false */
    public static function fileperms(string $path)
    {
        return @fileperms($path);
    }

    /** @return int|false */
    public static function fileowner(string $path)
    {
        return @fileowner($path);
    }

    public static function chmod(string $path, int $mode): bool
    {
        return @chmod($path, $mode);
    }

    public static function mkdir(string $path, int $mode = 0777, bool $recursive = false): bool
    {
        return @mkdir($path, $mode, $recursive);
    }

    /** @return string|false */
    public static function fileGetContents(string $path)
    {
        return @file_get_contents($path);
    }
}
