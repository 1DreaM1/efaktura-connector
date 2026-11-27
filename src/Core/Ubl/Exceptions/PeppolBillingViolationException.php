<?php

namespace DreaM1\EfakturaConnector\Core\Ubl\Exceptions;

use DreaM1\EfakturaConnector\Core\Ubl\Validation\PeppolBillingRule;

/**
 * Shared base of the standalone Peppol BIS Billing check failures; carries the violated rules.
 */
abstract class PeppolBillingViolationException extends UblValidationException
{
    /** @var string[] */
    private array $violatedRules;

    /**
     * Creates the exception for a checked subject and its violated rules.
     *
     * @param string   $subject       Checked subject used in the message, for example "document".
     * @param string[] $violatedRules PeppolBillingRule or PeppolBillingDraftRule values
     */
    public function __construct(string $subject, array $violatedRules)
    {
        parent::__construct('The ' . $subject . ' does not meet the Peppol BIS Billing '
            . PeppolBillingRule::SPECIFICATION_VERSION . ' rules: '
            . implode(', ', $violatedRules) . '.');
        $this->violatedRules = array_values($violatedRules);
    }

    /**
     * Returns the Peppol billing rules that were violated.
     *
     * @return string[] Rule values, in the order they were checked
     */
    public function violatedRules(): array
    {
        return $this->violatedRules;
    }
}
