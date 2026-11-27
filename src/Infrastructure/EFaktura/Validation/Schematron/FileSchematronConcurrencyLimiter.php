<?php

namespace DreaM1\EfakturaConnector\Infrastructure\EFaktura\Validation\Schematron;

use DreaM1\EfakturaConnector\Core\Ubl\Exceptions\LocalValidationInfrastructureException;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Coordination\LockFilesystem;
use DreaM1\EfakturaConnector\Infrastructure\ResourcePath;
use DreaM1\EfakturaConnector\Support\Exceptions\InvalidInputException;

/** Limits simultaneous validation jobs among PHP workers sharing its lock directory. */
final class FileSchematronConcurrencyLimiter implements SchematronConcurrencyLimiterInterface
{
    /** Default child directory used for Schematron worker lock files. */
    private const LOCK_DIRECTORY_NAME = 'efaktura-schematron-locks';
    /** Delay between checks for an available validation slot, in microseconds. */
    private const POLL_INTERVAL_MICROSECONDS = 10000;

    private int $maxConcurrentValidations;
    private SchematronLockFileStore $lockFileStore;

    /**
     * Creates a file-backed concurrency limiter for workers using the same lock directory.
     *
     * @param  int                   $maxConcurrentValidations Maximum validations allowed among workers sharing the lock directory.
     * @param  string|null           $lockDirectory            Private directory for cross-process lock files; defaults to a subdirectory in the system temp directory.
     * @throws InvalidInputException when the concurrency limit or lock directory is invalid
     */
    public function __construct(int $maxConcurrentValidations, ?string $lockDirectory = null)
    {
        if ($maxConcurrentValidations < 1) {
            throw new InvalidInputException('Maximum concurrent Schematron validations must be positive.');
        }

        $lockDirectory = $lockDirectory ?? ResourcePath::join(sys_get_temp_dir(), self::LOCK_DIRECTORY_NAME);

        if ($lockDirectory === '') {
            throw new InvalidInputException('Schematron concurrency lock directory must not be empty.');
        }

        $lockDirectory = rtrim($lockDirectory, '/\\');

        if ($lockDirectory === '') {
            throw new InvalidInputException('Schematron concurrency lock directory must not be a filesystem root.');
        }

        $this->maxConcurrentValidations = $maxConcurrentValidations;
        $this->lockFileStore            = new SchematronLockFileStore($lockDirectory);
    }

    /**
     * Waits for one exclusive validation slot shared across working directories on this host.
     *
     * @param  string                                 $workingDirectory Existing process working directory.
     * @param  int                                    $timeoutSeconds   Maximum time to wait for a free slot.
     * @return SchematronConcurrencyPermitInterface   Permit that releases the slot when released or destroyed.
     * @throws InvalidInputException                  when the wait timeout is negative
     * @throws LocalValidationInfrastructureException when file locks are unavailable, busy or unsafe
     */
    public function acquire(string $workingDirectory, int $timeoutSeconds): SchematronConcurrencyPermitInterface
    {
        if ($timeoutSeconds < 0) {
            throw new InvalidInputException('Schematron concurrency wait timeout must not be negative.');
        }

        if (! function_exists('flock')) {
            throw new LocalValidationInfrastructureException(
                'Local Peppol validation requires PHP file-lock support.'
            );
        }

        $lockDirectory = $this->lockFileStore->directoryFor($workingDirectory);
        $deadline      = microtime(true) + $timeoutSeconds;

        do {
            // Each slot is a non-blocking file lock, so another worker can be tried immediately when one is busy.
            for ($slot = 0; $slot < $this->maxConcurrentValidations; $slot++) {
                $handle = $this->lockFileStore->openSlot($lockDirectory, $slot);

                if (LockFilesystem::flock($handle, LOCK_EX | LOCK_NB)) {
                    return new FileSchematronConcurrencyPermit($handle);
                }

                LockFilesystem::fclose($handle);
            }

            if (microtime(true) >= $deadline) {
                throw new LocalValidationInfrastructureException(
                    'Local Peppol validation concurrency limit is busy.'
                );
            }

            // Retry until a slot opens or the single acquisition deadline expires.
            usleep(self::POLL_INTERVAL_MICROSECONDS);
        } while (true);
    }
}
