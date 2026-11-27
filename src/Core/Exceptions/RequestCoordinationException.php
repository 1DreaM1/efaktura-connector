<?php

namespace DreaM1\EfakturaConnector\Core\Exceptions;

use Throwable;

/** Local request coordination failed before the guarded HTTP operation could run. */
final class RequestCoordinationException extends EFakturaException
{
    /**
     * Creates a request-coordination failure while retaining its infrastructure cause.
     *
     * @param Throwable|null $previous Previous exception that caused this failure, when available.
     */
    public function __construct(?Throwable $previous = null)
    {
        parent::__construct('Local HTTP request coordination could not be established.', $previous);
    }
}
