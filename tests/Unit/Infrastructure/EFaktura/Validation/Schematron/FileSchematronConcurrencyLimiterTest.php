<?php

namespace DreaM1\EfakturaConnector\Tests\Unit\Infrastructure\EFaktura\Validation\Schematron;

use DreaM1\EfakturaConnector\Core\Ubl\Exceptions\LocalValidationInfrastructureException;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Validation\Schematron\FileSchematronConcurrencyLimiter;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Validation\Schematron\SchematronConcurrencyPermitInterface;
use DreaM1\EfakturaConnector\Infrastructure\ResourcePath;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/** Tests file-backed limits for concurrent Schematron validations. */
class FileSchematronConcurrencyLimiterTest extends TestCase
{
    public function testSeparateLimiterInstancesShareSlotsAcrossWorkingDirectories(): void
    {
        $firstWorkingDirectory  = ResourcePath::join(sys_get_temp_dir(), 'efaktura-work-one-' . bin2hex(random_bytes(8)));
        $secondWorkingDirectory = ResourcePath::join(sys_get_temp_dir(), 'efaktura-work-two-' . bin2hex(random_bytes(8)));
        $lockDirectory          = ResourcePath::join(sys_get_temp_dir(), 'efaktura-lock-test-' . bin2hex(random_bytes(8)));
        mkdir($firstWorkingDirectory, 0700);
        mkdir($secondWorkingDirectory, 0700);

        $first            = new FileSchematronConcurrencyLimiter(1, $lockDirectory);
        $second           = new FileSchematronConcurrencyLimiter(1, $lockDirectory);
        $firstPermit      = null;
        $unexpectedPermit = null;
        $nextPermit       = null;

        try {
            $firstPermit = $first->acquire($firstWorkingDirectory, 0);

            try {
                $unexpectedPermit = $second->acquire($secondWorkingDirectory, 0);
                $this->fail('A second validation must not acquire a full slot.');
            } catch (LocalValidationInfrastructureException $exception) {
                $this->assertStringContainsString('concurrency limit is busy', $exception->getMessage());
            }

            $firstPermit->release();
            $firstPermit = null;
            $nextPermit  = $second->acquire($secondWorkingDirectory, 0);
            $this->assertInstanceOf(SchematronConcurrencyPermitInterface::class, $nextPermit);
        } finally {
            if ($firstPermit !== null) {
                $firstPermit->release();
            }

            if ($unexpectedPermit !== null) {
                $unexpectedPermit->release();
            }

            if ($nextPermit !== null) {
                $nextPermit->release();
            }

            $this->removeLockFiles($lockDirectory);
            rmdir($firstWorkingDirectory);
            rmdir($secondWorkingDirectory);
        }
    }

    public function testCreatesMissingNestedLockDirectory(): void
    {
        $workingDirectory = sys_get_temp_dir();
        $parentDirectory  = ResourcePath::join(
            sys_get_temp_dir(),
            'efaktura-nested-lock-test-' . bin2hex(random_bytes(8))
        );
        $lockDirectory = ResourcePath::join($parentDirectory, 'nested/locks');
        $permit        = null;

        try {
            $permit = (new FileSchematronConcurrencyLimiter(1, $lockDirectory))->acquire($workingDirectory, 0);
            $this->assertInstanceOf(SchematronConcurrencyPermitInterface::class, $permit);
        } finally {
            if ($permit !== null) {
                $permit->release();
            }

            $this->removeLockFiles($lockDirectory);
            @rmdir(dirname($lockDirectory));
            @rmdir($parentDirectory);
        }
    }

    public function testRejectsNonPositiveConcurrencyLimit(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new FileSchematronConcurrencyLimiter(0);
    }

    public function testRejectsSymbolicLinkLockDirectory(): void
    {
        $baseDirectory   = ResourcePath::join(sys_get_temp_dir(), 'efaktura-link-test-' . bin2hex(random_bytes(8)));
        $targetDirectory = ResourcePath::join($baseDirectory, 'target');
        $linkDirectory   = ResourcePath::join($baseDirectory, 'link');
        mkdir($baseDirectory, 0700);
        mkdir($targetDirectory, 0700);

        if (! @symlink($targetDirectory, $linkDirectory)) {
            rmdir($targetDirectory);
            rmdir($baseDirectory);
            $this->markTestSkipped('Symbolic links are unavailable in this environment.');
        }

        try {
            (new FileSchematronConcurrencyLimiter(1, $linkDirectory))->acquire(sys_get_temp_dir(), 0);
            $this->fail('A symbolic link must not be used as the lock directory.');
        } catch (LocalValidationInfrastructureException $exception) {
            $this->assertStringContainsString('must not be a symbolic link', $exception->getMessage());
        } finally {
            unlink($linkDirectory);
            rmdir($targetDirectory);
            rmdir($baseDirectory);
        }
    }

    public function testRejectsSymbolicLinkLockFileWithoutOpeningTarget(): void
    {
        $workingDirectory = sys_get_temp_dir();
        $lockDirectory    = ResourcePath::join(sys_get_temp_dir(), 'efaktura-lock-link-test-' . bin2hex(random_bytes(8)));
        $targetFile       = $lockDirectory . '-target';
        mkdir($lockDirectory, 0700);
        file_put_contents($targetFile, 'unchanged');
        $lockFile = ResourcePath::join($lockDirectory, 'slot-0.lock');

        if (! @symlink($targetFile, $lockFile)) {
            unlink($targetFile);
            rmdir($lockDirectory);
            $this->markTestSkipped('Symbolic links are unavailable in this environment.');
        }

        try {
            (new FileSchematronConcurrencyLimiter(1, $lockDirectory))->acquire($workingDirectory, 0);
            $this->fail('A symbolic link must not be used as the lock file.');
        } catch (LocalValidationInfrastructureException $exception) {
            $this->assertStringContainsString('must not be a symbolic link', $exception->getMessage());
            $this->assertSame('unchanged', file_get_contents($targetFile));
        } finally {
            unlink($lockFile);
            unlink($targetFile);
            rmdir($lockDirectory);
        }
    }

    public function testRejectsExistingLockDirectoryWithBroadPermissions(): void
    {
        if (DIRECTORY_SEPARATOR === '\\') {
            $this->markTestSkipped('POSIX permission bits are not available on Windows.');
        }

        $workingDirectory = sys_get_temp_dir();
        $lockDirectory    = ResourcePath::join(
            sys_get_temp_dir(),
            'efaktura-lock-mode-test-' . bin2hex(random_bytes(8))
        );
        mkdir($lockDirectory, 0755);

        try {
            (new FileSchematronConcurrencyLimiter(1, $lockDirectory))->acquire($workingDirectory, 0);
            $this->fail('A lock directory with broad permissions must be rejected.');
        } catch (LocalValidationInfrastructureException $exception) {
            $this->assertStringContainsString('not private', $exception->getMessage());
        } finally {
            chmod($lockDirectory, 0700);
            rmdir($lockDirectory);
        }
    }

    private function removeLockFiles(string $lockDirectory): void
    {
        $lockFile = ResourcePath::join($lockDirectory, 'slot-0.lock');

        if (is_file($lockFile)) {
            unlink($lockFile);
        }

        if (is_dir($lockDirectory)) {
            rmdir($lockDirectory);
        }
    }
}
