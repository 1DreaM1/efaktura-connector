<?php

namespace DreaM1\EfakturaConnector\Tests\Unit\Infrastructure\EFaktura\Coordination;

use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Coordination\SecureLockFileStorage;
use DreaM1\EfakturaConnector\Infrastructure\ResourcePath;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/** Tests secure directory and lock file validation. */
class SecureLockFileStorageTest extends TestCase
{
    public function testPreparesDirectoryAndSecuresAnOpenedLockFile(): void
    {
        $root            = ResourcePath::join(sys_get_temp_dir(), 'efaktura-storage-test-' . bin2hex(random_bytes(8)));
        $parentDirectory = ResourcePath::join($root, 'parent');
        $lockDirectory   = ResourcePath::join($parentDirectory, 'locks');
        $lockFile        = null;
        $handle          = null;
        mkdir($parentDirectory, 0700, true);

        try {
            $storage            = new SecureLockFileStorage();
            $canonicalDirectory = $storage->preparePrivateDirectory($lockDirectory, false, true);
            $this->assertSame(realpath($lockDirectory), $canonicalDirectory);

            $lockFile = ResourcePath::join($canonicalDirectory, 'scope.lock');
            $handle   = fopen($lockFile, 'c');
            $this->assertIsResource($handle);

            $storage->secureAndValidateLockFile($handle, $lockFile);

            if (! $storage->isWindows()) {
                $this->assertSame(0700, fileperms($canonicalDirectory) & 0777);
                $this->assertSame(0600, fileperms($lockFile) & 0777);
            }
        } finally {
            if (is_resource($handle)) {
                fclose($handle);
            }

            if (is_string($lockFile) && is_file($lockFile)) {
                unlink($lockFile);
            }

            if (is_dir($lockDirectory)) {
                rmdir($lockDirectory);
            }

            if (is_dir($parentDirectory)) {
                rmdir($parentDirectory);
            }

            if (is_dir($root)) {
                rmdir($root);
            }
        }
    }

    public function testRejectsHardLinkedLockFilesBeforeChangingTheirPermissions(): void
    {
        if (DIRECTORY_SEPARATOR === '\\' || ! function_exists('link')) {
            $this->markTestSkipped('Hard-link checks require POSIX file metadata and link support.');
        }

        $root          = ResourcePath::join(sys_get_temp_dir(), 'efaktura-hardlink-test-' . bin2hex(random_bytes(8)));
        $lockDirectory = ResourcePath::join($root, 'locks');
        $lockFile      = ResourcePath::join($lockDirectory, 'scope.lock');
        $hardLink      = ResourcePath::join($root, 'outside.lock');
        mkdir($lockDirectory, 0700, true);
        file_put_contents($lockFile, 'synthetic');

        try {
            if (! @link($lockFile, $hardLink)) {
                $this->markTestSkipped('Hard links are unavailable in this environment.');
            }

            chmod($lockFile, 0644);
            $handle = fopen($lockFile, 'c');
            $this->assertIsResource($handle);

            try {
                (new SecureLockFileStorage())->secureAndValidateLockFile($handle, $lockFile);
                $this->fail('A hard-linked file must not be accepted as a lock.');
            } catch (RuntimeException $exception) {
                $this->assertStringContainsString('one regular file', $exception->getMessage());
            } finally {
                fclose($handle);
            }

            // Rejection must happen before chmod; changing permissions through this hard link would affect its alias too.
            $this->assertSame(0644, fileperms($lockFile) & 0777);
            $this->assertSame('synthetic', file_get_contents($hardLink));
        } finally {
            if (is_file($hardLink)) {
                unlink($hardLink);
            }

            if (is_file($lockFile)) {
                unlink($lockFile);
            }

            if (is_dir($lockDirectory)) {
                rmdir($lockDirectory);
            }

            if (is_dir($root)) {
                rmdir($root);
            }
        }
    }
}
