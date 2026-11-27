<?php

namespace DreaM1\EfakturaConnector\Core\Ubl\Exceptions;

/**
 * The document is not a UBL Invoice or CreditNote.
 */
final class UnsupportedDocumentTypeException extends UblValidationException
{
    public function __construct()
    {
        parent::__construct('A UBL Invoice or CreditNote is required.');
    }
}
