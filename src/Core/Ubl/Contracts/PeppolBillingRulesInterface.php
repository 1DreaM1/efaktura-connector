<?php

namespace DreaM1\EfakturaConnector\Core\Ubl\Contracts;

use DreaM1\EfakturaConnector\Core\Ubl\Exceptions\PeppolBillingRulesException;
use DreaM1\EfakturaConnector\Domain\Ubl\UblDocumentHeader;

/**
 * Lightweight Peppol BIS Billing checks that complement the full Schematron rules.
 *
 * They need neither Java nor the XSD, so a host can check them even with local XSD / Schematron
 * validation turned off. Implementations should avoid repeating checks already performed by Schematron.
 */
interface PeppolBillingRulesInterface extends UblValidationRuleVersionProviderInterface
{
    /**
     * Returns the violated standalone Peppol billing rules for the document header.
     *
     * @param  UblDocumentHeader $header Header from the UBL document being checked.
     * @return string[]          violated PeppolBillingRule values not covered by bundled Schematron rules
     */
    public function violations(UblDocumentHeader $header): array;

    /**
     * Throws when the document header violates a Peppol billing rule.
     *
     * @param  UblDocumentHeader           $header Header from the UBL document being checked.
     * @return void
     * @throws PeppolBillingRulesException listing every violated rule
     */
    public function assertSatisfied(UblDocumentHeader $header): void;
}
