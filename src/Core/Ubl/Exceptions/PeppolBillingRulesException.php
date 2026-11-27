<?php

namespace DreaM1\EfakturaConnector\Core\Ubl\Exceptions;

/**
 * The document violates a standalone Peppol BIS Billing check for an outgoing document.
 */
final class PeppolBillingRulesException extends PeppolBillingViolationException
{
    /**
     * Creates a new PeppolBillingRulesException value.
     * @param string[] $violatedRules PeppolBillingRule values
     */
    public function __construct(array $violatedRules)
    {
        parent::__construct('document', $violatedRules);
    }
}
