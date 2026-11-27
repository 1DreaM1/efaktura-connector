<?php

namespace DreaM1\EfakturaConnector\Infrastructure\Ubl\Validation;

use DreaM1\EfakturaConnector\Core\Ubl\Contracts\PeppolBillingRulesInterface;
use DreaM1\EfakturaConnector\Core\Ubl\Exceptions\PeppolBillingRulesException;
use DreaM1\EfakturaConnector\Core\Ubl\Validation\PeppolBillingRule;
use DreaM1\EfakturaConnector\Domain\Ubl\UblDocumentHeader;
use DreaM1\EfakturaConnector\Domain\Ubl\UblDocumentRoot;

/** Checks BR-CO-25: a positive Invoice requires DueDate or PaymentTerms. */
class PeppolBillingHeaderRules implements PeppolBillingRulesInterface
{
    /**
     * Returns the rules violated by the document header.
     *
     * @param  UblDocumentHeader $header UBL document header being checked.
     * @return string[]          PeppolBillingRule identifiers in rule declaration order.
     */
    public function violations(UblDocumentHeader $header): array
    {
        // Ignore CreditNote roots; for Invoice, check PayableAmount together with due-date/payment-terms presence.
        $documentRoot = $header->documentRoot();
        $isInvoice    = $documentRoot !== null && $documentRoot->value() === UblDocumentRoot::INVOICE;

        if (
            PeppolBillingDueDateOrTermsRule::isViolated(
                $isInvoice,
                $this->isAmountDue($header),
                $header->hasDueDate(),
                $header->hasPaymentTerms()
            )
        ) {
            return [PeppolBillingRule::PAYMENT_DUE_DATE_OR_TERMS];
        }

        return [];
    }

    /**
     * Returns the Peppol Billing rule-set version applied by this checker.
     *
     * @return array<string, string>
     */
    public function ruleVersions(): array
    {
        return [PeppolBillingRule::RULESET_ID => PeppolBillingRule::SPECIFICATION_VERSION];
    }

    /**
     * Throws when the document does not satisfy the configured rules.
     *
     * @param  UblDocumentHeader           $header Header from the UBL document being checked.
     * @return void
     * @throws PeppolBillingRulesException when the header violates a checked Peppol Billing rule.
     */
    public function assertSatisfied(UblDocumentHeader $header): void
    {
        $violations = $this->violations($header);

        if ($violations !== []) {
            throw new PeppolBillingRulesException($violations);
        }
    }

    /** Reads LegalMonetaryTotal/PayableAmount; malformed decimals are left to Schematron validation. */
    private function isAmountDue(UblDocumentHeader $header): bool
    {
        $payableAmount = $header->payableAmount();

        // Invalid decimals are reported by PAYABLE_AMOUNT; only a valid positive amount activates this rule.
        return PeppolBillingAmount::isPositive($payableAmount);
    }
}
