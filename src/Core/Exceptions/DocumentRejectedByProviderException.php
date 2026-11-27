<?php

namespace DreaM1\EfakturaConnector\Core\Exceptions;

/**
 * The provider validated the document and answered that it is not valid, without listing the reasons.
 */
final class DocumentRejectedByProviderException extends DocumentPreflightException
{
    public function __construct()
    {
        parent::__construct('The provider rejected the document as invalid.');
    }
}
