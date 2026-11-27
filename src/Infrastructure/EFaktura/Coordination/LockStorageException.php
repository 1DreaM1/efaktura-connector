<?php

namespace DreaM1\EfakturaConnector\Infrastructure\EFaktura\Coordination;

use DreaM1\EfakturaConnector\Support\Exceptions\ConnectorThrowable;
use RuntimeException;

/** A local lock file or its directory is missing, unsafe, or could not be opened. */
final class LockStorageException extends RuntimeException implements ConnectorThrowable
{
}
