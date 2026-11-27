<?php

namespace DreaM1\EfakturaConnector\Core\Ubl\Exceptions;

/** The draft does not meet the Peppol Billing rules checked before serialization. */
final class PeppolBillingDraftRulesException extends PeppolBillingViolationException
{
    /**
     * Creates an exception listing the rules violated by a draft.
     *
     * @param string[] $violatedRules PeppolBillingDraftRule values
     */
    public function __construct(array $violatedRules)
    {
        parent::__construct('UBL draft', $violatedRules);
    }
}
