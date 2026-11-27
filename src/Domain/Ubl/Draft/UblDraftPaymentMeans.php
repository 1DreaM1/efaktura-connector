<?php

namespace DreaM1\EfakturaConnector\Domain\Ubl\Draft;

use DreaM1\EfakturaConnector\Domain\Ubl\CodeList\PaymentMeansCode;

/** cac:PaymentMeans with an optional payee account. */
final class UblDraftPaymentMeans
{
    /** Payment method written to cac:PaymentMeans/cbc:PaymentMeansCode. */
    private PaymentMeansCode $code;
    /** Customer remittance reference written to cac:PaymentMeans/cbc:PaymentID. */
    private ?UblDraftIdentifier $paymentId;
    /** Payee account ID written to cac:PaymentMeans/cac:PayeeFinancialAccount/cbc:ID. */
    private ?UblDraftIdentifier $accountId;
    /** Optional account name written to cac:PayeeFinancialAccount/cbc:Name. */
    private ?string $accountName;
    /** Optional payment method name written to PaymentMeansCode/@name. */
    private ?string $name = null;
    /** Optional bank branch ID written under PayeeFinancialAccount/FinancialInstitutionBranch/ID. */
    private ?UblDraftIdentifier $financialInstitutionBranchId = null;
    /** Optional card details written to cac:PaymentMeans/cac:CardAccount. */
    private ?UblDraftPaymentCard $cardAccount = null;
    /** Optional direct-debit data written to cac:PaymentMeans/cac:PaymentMandate. */
    private ?UblDraftPaymentMandate $paymentMandate = null;

    /**
     * Creates payment instructions for the invoice or credit note.
     * @param PaymentMeansCode        $code        Payment method code, such as bank transfer, from CodeList\PaymentMeansCode.
     * @param UblDraftIdentifier|null $paymentId   Remittance reference the customer should include with the payment.
     * @param UblDraftIdentifier|null $accountId   Payee financial account identifier, usually an IBAN for bank transfer.
     * @param string|null             $accountName Name associated with the payee's financial account.
     */
    public function __construct(
        PaymentMeansCode $code,
        ?UblDraftIdentifier $paymentId = null,
        ?UblDraftIdentifier $accountId = null,
        ?string $accountName = null
    ) {
        $this->code        = $code;
        $this->paymentId   = $paymentId;
        $this->accountId   = $accountId;
        $this->accountName = UblDraftText::emptyToNull($accountName);
    }

    /**
     * Returns the code identifying how the customer can pay.
     *
     * @return PaymentMeansCode
     */
    public function code(): PaymentMeansCode
    {
        return $this->code;
    }

    /**
     * Returns the remittance reference the customer should use when paying.
     *
     * @return UblDraftIdentifier|null
     */
    public function paymentId(): ?UblDraftIdentifier
    {
        return $this->paymentId;
    }

    /**
     * Returns the payee's financial account identifier, usually an IBAN.
     *
     * @return UblDraftIdentifier|null
     */
    public function accountId(): ?UblDraftIdentifier
    {
        return $this->accountId;
    }

    /**
     * Returns the name associated with the payee's financial account.
     *
     * @return string|null
     */
    public function accountName(): ?string
    {
        return $this->accountName;
    }

    /**
     * Returns a copy with the payment means name, clearing it when null or blank.
     *
     * @param  string|null $name Name of the payment method.
     * @return self
     */
    public function withName(?string $name): self
    {
        $payment       = clone $this;
        $payment->name = UblDraftText::emptyToNull($name);

        return $payment;
    }

    /**
     * Returns a copy with the payee bank branch identifier, clearing it when null or blank.
     *
     * @param  UblDraftIdentifier|null $id Identifier of the financial institution branch.
     * @return self
     */
    public function withFinancialInstitutionBranchId(?UblDraftIdentifier $id): self
    {
        $payment                               = clone $this;
        $payment->financialInstitutionBranchId = $id;

        return $payment;
    }

    /**
     * Returns a copy with card details, or clears the card details when null.
     *
     * @param  UblDraftPaymentCard|null $card Card details for the payment.
     * @return self
     */
    public function withCardAccount(?UblDraftPaymentCard $card): self
    {
        $payment              = clone $this;
        $payment->cardAccount = $card;

        return $payment;
    }

    /**
     * Returns a copy with direct debit mandate details, or clears them when null.
     *
     * @param  UblDraftPaymentMandate|null $mandate Mandate details for direct debit.
     * @return self
     */
    public function withPaymentMandate(?UblDraftPaymentMandate $mandate): self
    {
        $payment                 = clone $this;
        $payment->paymentMandate = $mandate;

        return $payment;
    }

    /** Returns the payment means name, or null when it is omitted. */
    public function name(): ?string
    {
        return $this->name;
    }

    /** Returns the payee bank branch identifier, or null when it is omitted. */
    public function financialInstitutionBranchId(): ?UblDraftIdentifier
    {
        return $this->financialInstitutionBranchId;
    }

    /** Returns the card account details, or null when this is not a card payment. */
    public function cardAccount(): ?UblDraftPaymentCard
    {
        return $this->cardAccount;
    }

    /** Returns the direct debit mandate details, or null when they are omitted. */
    public function paymentMandate(): ?UblDraftPaymentMandate
    {
        return $this->paymentMandate;
    }
}
