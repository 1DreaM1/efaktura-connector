<?php

namespace DreaM1\EfakturaConnector\Core\Ubl\Exceptions;

use DreaM1\EfakturaConnector\Core\Exceptions\EFakturaException;

/**
 * The UBL document itself is invalid:
 *
 *   InvalidXmlException               unsafe or malformed XML
 *   UnsupportedDocumentTypeException  not a UBL Invoice or CreditNote
 *   SchemaValidationException         failed UBL XSD validation (issues)
 *   PeppolBillingViolationException   failed a standalone Peppol BIS Billing check (violated rules); abstract base of:
 *     PeppolBillingRulesException       the header check of a parsed document
 *     PeppolBillingDraftRulesException  the check of a draft before serialization
 */
abstract class UblValidationException extends EFakturaException
{
}
