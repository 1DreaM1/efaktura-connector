<?php

namespace DreaM1\EfakturaConnector\Core\Exceptions;

/**
 * The recipient has not published support for this document type on the Peppol network.
 */
final class RecipientUnsupportedException extends DocumentPreflightException
{
    public function __construct()
    {
        parent::__construct('The recipient does not accept this document type.');
    }
}
