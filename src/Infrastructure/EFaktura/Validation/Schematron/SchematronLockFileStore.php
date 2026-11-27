<?php

namespace DreaM1\EfakturaConnector\Infrastructure\EFaktura\Validation\Schematron;

use DreaM1\EfakturaConnector\Core\Ubl\Exceptions\LocalValidationInfrastructureException;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Coordination\LockFilesystem;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Coordination\LockStorageException;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Coordination\SecureLockFileStorage;
use DreaM1\EfakturaConnector\Infrastructure\ResourcePath;
use RuntimeException;

/** @internal Creates and validates the private files used by the Schematron concurrency limiter. */
final class SchematronLockFileStore
{
    private string $lockDirectory;
    private SecureLockFileStorage $fileStorage;

    /**
     * Creates a store rooted in the directory reserved for validation locks.
     *
     * @param string $lockDirectory Root directory reserved for private concurrency lock files.
     */
    public function __construct(string $lockDirectory)
    {
        $this->lockDirectory = $lockDirectory;
        $this->fileStorage   = new SecureLockFileStorage();
    }

    /**
     * Returns the shared private lock directory after validating the process working directory.
     *
     * @param  string                                 $workingDirectory Existing readable process working directory.
     * @return string                                 Shared private directory for workers using this lock directory.
     * @throws LocalValidationInfrastructureException when either directory cannot be secured
     */
    public function directoryFor(string $workingDirectory): string
    {
        if (! is_dir($workingDirectory) || ! is_readable($workingDirectory)) {
            throw new LocalValidationInfrastructureException(
                'Local Peppol validation working directory is unavailable.'
            );
        }

        return $this->prepareLockDirectory($this->lockDirectory);
    }

    /**
     * Opens and secures one slot file before returning its handle for flock().
     *
     * @param  string                                 $lockDirectory Private directory returned by directoryFor().
     * @param  int                                    $slot          Zero-based concurrency slot number.
     * @return resource
     * @throws LocalValidationInfrastructureException when the slot file cannot be safely opened
     */
    public function openSlot(string $lockDirectory, int $slot)
    {
        $path   = ResourcePath::join($lockDirectory, 'slot-' . $slot . '.lock');
        $handle = null;
        try {
            $this->fileStorage->assertNotSymbolicLink($path, 'path');
            // Create a new slot exclusively when possible; otherwise open its existing regular lock file.
            $handle = LockFilesystem::fopen($path, 'x');

            if (! is_resource($handle)) {
                $this->fileStorage->assertNotSymbolicLink($path, 'path');
                $handle = LockFilesystem::fopen($path, 'c');
            }

            if (! is_resource($handle)) {
                throw new LockStorageException('The file lock could not be opened.');
            }

            $this->fileStorage->secureAndValidateLockFile($handle, $path);
        } catch (LockStorageException $exception) {
            if (is_resource($handle)) {
                fclose($handle);
            }

            throw new LocalValidationInfrastructureException(
                'Local Peppol validation concurrency lock is unavailable or unsafe: '
                    . $exception->getMessage(),
                $exception
            );
        }

        return $handle;
    }

    /** Creates or validates one private directory without following a link at its final path. */
    private function prepareLockDirectory(string $path): string
    {
        try {
            return $this->fileStorage->preparePrivateDirectory($path, true, false);
        } catch (LockStorageException $exception) {
            throw new LocalValidationInfrastructureException(
                'Local Peppol validation concurrency lock directory is unavailable or unsafe: '
                    . $exception->getMessage(),
                $exception
            );
        }
    }
}
