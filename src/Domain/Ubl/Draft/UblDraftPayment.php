<?php

namespace DreaM1\EfakturaConnector\Domain\Ubl\Draft;

/**
 * The payment instructions of a UblDocumentDraft: payment means, terms and a separate payee.
 *
 * Immutable: every with*() returns a copy.
 */
final class UblDraftPayment
{
    /** @var UblDraftPaymentMeans[] Payment instructions written as cac:PaymentMeans elements. */
    private array $means = [];
    /** Payment instructions text written to cac:PaymentTerms/cbc:Note. */
    private ?string $termsNote = null;
    /** Separate recipient of payment, written to cac:PayeeParty. */
    private ?UblDraftPayee $payee = null;

    /** Returns a set with no payment instructions. */
    public static function none(): self
    {
        return new self();
    }

    /** Returns a copy with this as the only payment means entry, replacing any previous entries. */
    public function withPaymentMeans(UblDraftPaymentMeans $paymentMeans): self
    {
        $payment        = clone $this;
        $payment->means = [$paymentMeans];

        return $payment;
    }

    /** Returns a copy with one more payment means entry; existing entries are kept. */
    public function withAdditionalPaymentMeans(UblDraftPaymentMeans $paymentMeans): self
    {
        $payment          = clone $this;
        $payment->means[] = $paymentMeans;

        return $payment;
    }

    /** Returns a copy with the payment terms note, or without it when null or blank. */
    public function withTermsNote(?string $note): self
    {
        $payment            = clone $this;
        $payment->termsNote = UblDraftText::emptyToNull($note);

        return $payment;
    }

    /** Returns a copy with a separate payee, or without it when null. */
    public function withPayee(?UblDraftPayee $payee): self
    {
        $payment        = clone $this;
        $payment->payee = $payee;

        return $payment;
    }

    /** Returns the first payment means entry, or null when there is none. */
    public function paymentMeans(): ?UblDraftPaymentMeans
    {
        return $this->means[0] ?? null;
    }

    /**
     * Returns payment means entries in the order they were added.
     *
     * @return UblDraftPaymentMeans[]
     */
    public function paymentMeansList(): array
    {
        return $this->means;
    }

    /** Returns the payment terms note, or null when it is omitted. */
    public function termsNote(): ?string
    {
        return $this->termsNote;
    }

    /** Returns the separate payee, or null when the seller is also the payee. */
    public function payee(): ?UblDraftPayee
    {
        return $this->payee;
    }
}
