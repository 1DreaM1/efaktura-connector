<?php

namespace DreaM1\EfakturaConnector\Core\Ubl\Exceptions;

use DreaM1\EfakturaConnector\Core\Exceptions\EFakturaException;
use Throwable;

/**
 * Local UBL / Peppol validation could not run (missing Java, Saxon resources, process failure, …).
 * Says nothing about the validity of the document itself; the message says what to fix.
 */
final class LocalValidationInfrastructureException extends EFakturaException
{
    /**
     * @param string         $message  Message or diagnostic text associated with the result.
     * @param Throwable|null $previous Previous exception that caused this failure, when available.
     */
    public function __construct(string $message, ?Throwable $previous = null)
    {
        parent::__construct($message, $previous);
    }
}
