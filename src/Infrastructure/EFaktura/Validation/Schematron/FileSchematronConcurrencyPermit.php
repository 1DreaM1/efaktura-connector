<?php

namespace DreaM1\EfakturaConnector\Infrastructure\EFaktura\Validation\Schematron;

use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Coordination\FileLockResource;

/** Holds an exclusive flock until the validation completes or the PHP process exits. */
final class FileSchematronConcurrencyPermit implements SchematronConcurrencyPermitInterface
{
    private FileLockResource $lock;

    /**
     * Takes ownership of an exclusively locked validation slot file.
     *
     * @param resource $handle Exclusively locked slot file handle owned by this permit.
     */
    public function __construct($handle)
    {
        $this->lock = new FileLockResource($handle);
    }

    /** Releases the lock and closes its file handle; repeated calls are safe. */
    public function release(): void
    {
        $this->lock->release();
    }
}
