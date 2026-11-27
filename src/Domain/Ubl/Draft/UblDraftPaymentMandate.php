<?php

namespace DreaM1\EfakturaConnector\Domain\Ubl\Draft;

/** Direct debit mandate and optional payer account. */
final class UblDraftPaymentMandate
{
    /** Optional direct-debit mandate ID written to cac:PaymentMandate/cbc:ID. */
    private ?UblDraftIdentifier $id;
    /** Optional payer account ID written to cac:PaymentMandate/cac:PayerFinancialAccount/cbc:ID. */
    private ?UblDraftIdentifier $payerAccountId;

    /**
     * Creates direct debit mandate details with an optional payer account identifier.
     *
     * @param UblDraftIdentifier|null $id             Mandate identifier.
     * @param UblDraftIdentifier|null $payerAccountId Payer's financial account identifier.
     */
    public function __construct(?UblDraftIdentifier $id = null, ?UblDraftIdentifier $payerAccountId = null)
    {
        $this->id             = $id;
        $this->payerAccountId = $payerAccountId;
    }

    /** Returns the direct debit mandate identifier, or null when it is omitted. */
    public function id(): ?UblDraftIdentifier
    {
        return $this->id;
    }

    /** Returns the payer's account identifier, or null when it is omitted. */
    public function payerAccountId(): ?UblDraftIdentifier
    {
        return $this->payerAccountId;
    }
}
