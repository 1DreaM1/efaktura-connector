<?php

namespace DreaM1\EfakturaConnector\Core\Exceptions;

use DreaM1\EfakturaConnector\Core\Exceptions\EFakturaException;

/**
 * A remote document check answered successfully that the document does not pass:
 *
 *   RecipientUnsupportedException        the recipient cannot receive this document type
 *   DocumentRejectedByProviderException  the provider's validation said "not valid"
 *
 * A check whose provider call ended with an HTTP error throws ProviderErrorResponseException instead;
 * the host decides what that error means.
 */
abstract class DocumentPreflightException extends EFakturaException
{
}
