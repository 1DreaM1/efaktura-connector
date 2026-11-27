<?php

namespace DreaM1\EfakturaConnector\Domain\Ubl\Draft;

use DreaM1\EfakturaConnector\Domain\Ubl\LocalDate;

/**
 * The references a UblDocumentDraft makes to other documents and to the buyer's accounting.
 *
 * Immutable: every with*() returns a copy; null or blank text omits the value.
 */
final class UblDraftReferences
{
    /** Buyer-assigned reference written to root cbc:BuyerReference. */
    private ?string $buyerReference = null;
    /** Accounting cost code written to root cbc:AccountingCost. */
    private ?string $accountingCost = null;
    /** Buyer order identifier written to cac:OrderReference/cbc:ID. */
    private ?UblDraftIdentifier $orderReference = null;
    /** Seller's sales order identifier written to cac:OrderReference/cbc:SalesOrderID. */
    private ?UblDraftIdentifier $salesOrderReference = null;
    /** Related invoices, orders, projects, and additional documents. */
    private UblDraftDocumentReferences $documents;

    private function __construct()
    {
        $this->documents = UblDraftDocumentReferences::none();
    }

    /** Returns a set with no references. */
    public static function none(): self
    {
        return new self();
    }

    /** Returns a copy with the buyer reference, or without it when null or blank. */
    public function withBuyerReference(?string $buyerReference): self
    {
        $references                 = clone $this;
        $references->buyerReference = UblDraftText::emptyToNull($buyerReference);

        return $references;
    }

    /** Returns a copy with the accounting cost code, or without it when null or blank. */
    public function withAccountingCost(?string $accountingCost): self
    {
        $references                 = clone $this;
        $references->accountingCost = UblDraftText::emptyToNull($accountingCost);

        return $references;
    }

    /** Returns a copy with the buyer order reference, or without it when null. */
    public function withOrderReference(?UblDraftIdentifier $orderReference): self
    {
        $references                 = clone $this;
        $references->orderReference = $orderReference;

        return $references;
    }

    /** Returns a copy with the seller's sales order reference, or without it when null. */
    public function withSalesOrderReference(?UblDraftIdentifier $salesOrderReference): self
    {
        $references                      = clone $this;
        $references->salesOrderReference = $salesOrderReference;

        return $references;
    }

    /**
     * References the earlier invoice that a credit note or corrective document concerns.
     * Replaces any existing billing reference while preserving other document references.
     *
     * @param UblDocumentNumber $invoiceNumber Number of the referenced invoice.
     * @param LocalDate|null    $issueDate     Issue date of that invoice, when known.
     */
    public function withBillingReference(UblDocumentNumber $invoiceNumber, ?LocalDate $issueDate = null): self
    {
        $references            = clone $this;
        $references->documents = $this->documents->withBillingReference($invoiceNumber, $issueDate);

        return $references;
    }

    /** Returns a copy with one more document reference; existing references are kept. */
    public function withDocumentReference(UblDraftDocumentReference $reference): self
    {
        $references            = clone $this;
        $references->documents = $this->documents->withReference($reference);

        return $references;
    }

    /** Returns the buyer reference, or null when it is omitted. */
    public function buyerReference(): ?string
    {
        return $this->buyerReference;
    }

    /** Returns the accounting cost code, or null when it is omitted. */
    public function accountingCost(): ?string
    {
        return $this->accountingCost;
    }

    /** Returns the buyer order reference, or null when it is omitted. */
    public function orderReference(): ?UblDraftIdentifier
    {
        return $this->orderReference;
    }

    /** Returns the seller-assigned sales order reference, or null when it is omitted. */
    public function salesOrderReference(): ?UblDraftIdentifier
    {
        return $this->salesOrderReference;
    }

    /** Returns the number of the referenced invoice, or null when there is no billing reference. */
    public function billingReference(): ?UblDocumentNumber
    {
        $reference = $this->documents->firstBillingReference();

        return $reference !== null ? UblDocumentNumber::fromString($reference->id()->value()) : null;
    }

    /** Returns the issue date of the referenced invoice, or null when it is unknown or there is no billing reference. */
    public function billingReferenceIssueDate(): ?LocalDate
    {
        $reference = $this->documents->firstBillingReference();

        return $reference !== null ? $reference->issueDate() : null;
    }

    /**
     * Returns document references in the order they were added.
     *
     * @return UblDraftDocumentReference[]
     */
    public function documentReferences(): array
    {
        return $this->documents->items();
    }
}
