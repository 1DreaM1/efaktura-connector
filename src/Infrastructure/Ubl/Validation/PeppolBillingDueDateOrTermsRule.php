<?php

namespace DreaM1\EfakturaConnector\Infrastructure\Ubl\Validation;

/** BR-CO-25: a positive payable Invoice needs a due date or payment terms. */
final class PeppolBillingDueDateOrTermsRule
{
    /** Whether the BR-CO-25 requirement applies and both DueDate and PaymentTerms are absent. */
    public static function isViolated(
        bool $isInvoice,
        bool $hasPositivePayableAmount,
        bool $hasDueDate,
        bool $hasPaymentTerms
    ): bool {
        return $isInvoice
            && $hasPositivePayableAmount
            && ! $hasDueDate
            && ! $hasPaymentTerms;
    }
}
