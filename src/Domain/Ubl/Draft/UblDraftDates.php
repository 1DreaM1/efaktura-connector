<?php

namespace DreaM1\EfakturaConnector\Domain\Ubl\Draft;

use DreaM1\EfakturaConnector\Domain\Ubl\LocalDate;

/**
 * The optional dates and period of a UblDocumentDraft.
 *
 * Immutable: every with*() returns a copy; null omits the value.
 */
final class UblDraftDates
{
    /** Payment due date: root cbc:DueDate for Invoice or cac:PaymentMeans/cbc:PaymentDueDate for CreditNote. */
    private ?LocalDate $dueDate = null;
    /** Date on which the document's tax becomes applicable; root cbc:TaxPointDate. */
    private ?LocalDate $taxPointDate = null;
    /** Fallback actual delivery date written under cac:Delivery/cbc:ActualDeliveryDate. */
    private ?LocalDate $deliveryDate = null;
    /** Period covered by the whole document, written to cac:InvoicePeriod. */
    private ?UblDraftPeriod $invoicePeriod = null;

    /** Returns a set with no date or period. */
    public static function none(): self
    {
        return new self();
    }

    /** Returns a copy with the payment due date, or without it when null. */
    public function withDueDate(?LocalDate $dueDate): self
    {
        $dates          = clone $this;
        $dates->dueDate = $dueDate;

        return $dates;
    }

    /** Returns a copy with the tax point date, or without it when null. */
    public function withTaxPointDate(?LocalDate $taxPointDate): self
    {
        $dates               = clone $this;
        $dates->taxPointDate = $taxPointDate;

        return $dates;
    }

    /** Returns a copy with the fallback actual delivery date, or without it when null. */
    public function withDeliveryDate(?LocalDate $deliveryDate): self
    {
        $dates               = clone $this;
        $dates->deliveryDate = $deliveryDate;

        return $dates;
    }

    /** Returns a copy with the period covered by the document, or without it when null. */
    public function withInvoicePeriod(?UblDraftPeriod $period): self
    {
        $dates                = clone $this;
        $dates->invoicePeriod = $period;

        return $dates;
    }

    /** Returns the payment due date, or null when it is omitted. */
    public function dueDate(): ?LocalDate
    {
        return $this->dueDate;
    }

    /** Returns the tax point date, or null when it is omitted. */
    public function taxPointDate(): ?LocalDate
    {
        return $this->taxPointDate;
    }

    /** Returns the fallback actual delivery date, or null when it is omitted. */
    public function deliveryDate(): ?LocalDate
    {
        return $this->deliveryDate;
    }

    /** Returns the period covered by the document, or null when it is omitted. */
    public function invoicePeriod(): ?UblDraftPeriod
    {
        return $this->invoicePeriod;
    }
}
