<?php

namespace DreaM1\EfakturaConnector\Infrastructure\EFaktura\Coordination;

/** @internal Owns one acquired file-lock resource until release or destruction. */
final class FileLockResource
{
    /** @var resource|null */
    private $handle;

    /**
     * Takes ownership of an acquired file-lock handle.
     *
     * @param resource $handle Acquired lock handle.
     */
    public function __construct($handle)
    {
        $this->handle = $handle;
    }

    /** Releases any lock still held when its owner is destroyed. */
    public function __destruct()
    {
        $this->release();
    }

    /** Releases the owned lock and closes its handle; repeated calls are safe. */
    public function release(): void
    {
        if (! is_resource($this->handle)) {
            return;
        }

        $handle       = $this->handle;
        $this->handle = null;
        LockFilesystem::flock($handle, LOCK_UN);
        LockFilesystem::fclose($handle);
    }
}
