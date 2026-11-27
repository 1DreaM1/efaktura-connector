<?php

namespace DreaM1\EfakturaConnector\Domain\Ubl\Draft;

use DreaM1\EfakturaConnector\Domain\EFaktura\PeppolCustomizationIdentifier;
use DreaM1\EfakturaConnector\Domain\EFaktura\PeppolProcessIdentifier;
use DreaM1\EfakturaConnector\Domain\Ubl\Code\CurrencyCode;
use DreaM1\EfakturaConnector\Domain\Ubl\CodeList\DocumentTypeCode;
use DreaM1\EfakturaConnector\Domain\Ubl\CodeList\UblDocumentTypeCode;
use DreaM1\EfakturaConnector\Domain\Ubl\LocalDate;
use DreaM1\EfakturaConnector\Domain\Ubl\PeppolBillingProfile;
use DreaM1\EfakturaConnector\Domain\Ubl\UblDocumentRoot;
use DreaM1\EfakturaConnector\Support\Exceptions\InvalidInputException;
use DreaM1\EfakturaConnector\Support\TypedArray;

/**
 * Everything UblDocumentWriterInterface writes into a UBL Invoice or CreditNote.
 *
 * Immutable: every with*() returns a copy. Dates are validated LocalDate values; monetary values
 * are written as supplied and the business rules are checked by Peppol validation, not here.
 */
final class UblDocumentDraft
{
    /** Invoice or CreditNote root element to write. */
    private UblDocumentRoot $kind;
    /** cbc:InvoiceTypeCode or cbc:CreditNoteTypeCode, depending on the root kind. */
    private UblDocumentTypeCode $typeCode;
    /** Document number written to root cbc:ID. */
    private UblDocumentNumber $number;
    /** Issue date written to root cbc:IssueDate. */
    private LocalDate $issueDate;
    /** Currency code for document amounts, written to cbc:DocumentCurrencyCode. */
    private CurrencyCode $currency;
    /** Issuing party written under cac:AccountingSupplierParty. */
    private UblDraftParty $supplier;
    /** Billed party written under cac:AccountingCustomerParty. */
    private UblDraftParty $customer;
    /** Document VAT breakdown and legal monetary totals. */
    private UblDraftTotals $totals;
    /** Specification identifier written to root cbc:CustomizationID. */
    private PeppolCustomizationIdentifier $customizationId;
    /** Business process identifier written to root cbc:ProfileID. */
    private PeppolProcessIdentifier $profileId;
    /** Optional tax accounting currency written to root cbc:TaxCurrencyCode. */
    private ?CurrencyCode $taxCurrencyCode = null;
    /** @var string[] Free-text notes written as root cbc:Note elements. */
    private array $notes = [];
    /** Optional dates and the period covered by the document. */
    private UblDraftDates $dates;
    /** Buyer, order, billing and other document references. */
    private UblDraftReferences $references;
    /** Payment means, payment terms and a separate payee. */
    private UblDraftPayment $payment;
    /** Optional delivery date, location, address, and receiving party under cac:Delivery. */
    private ?UblDraftDelivery $delivery = null;
    /** Optional VAT representative written to cac:TaxRepresentativeParty. */
    private ?UblDraftTaxRepresentative $taxRepresentative = null;
    /** @var UblDraftAllowanceCharge[] Document-level discounts and charges written as cac:AllowanceCharge. */
    private array $allowanceCharges = [];
    /** @var UblDraftLine[] Goods or services written as InvoiceLine or CreditNoteLine aggregates. */
    private array $lines = [];

    /**
     * Creates a new UblDocumentDraft value.
     * @param UblDocumentRoot   $kind      UBL Invoice or CreditNote root.
     * @param UblDocumentNumber $number    Invoice or credit note number assigned by the supplier.
     * @param LocalDate         $issueDate Date the invoice or credit note was issued.
     * @param CurrencyCode      $currency  Canonical document currency code.
     * @param UblDraftParty     $supplier  Party issuing the invoice or credit note.
     * @param UblDraftParty     $customer  Party billed for the supplied goods or services.
     * @param UblDraftTotals    $totals    Document-level tax and payable amounts; the writer uses the supplied values.
     */
    public function __construct(
        UblDocumentRoot $kind,
        UblDocumentNumber $number,
        LocalDate $issueDate,
        CurrencyCode $currency,
        UblDraftParty $supplier,
        UblDraftParty $customer,
        UblDraftTotals $totals
    ) {
        $this->kind     = $kind;
        $this->typeCode = UblDocumentTypeCode::fromString($kind->isCreditNote()
            ? DocumentTypeCode::CREDIT_NOTE
            : DocumentTypeCode::COMMERCIAL_INVOICE);
        $this->number          = $number;
        $this->issueDate       = $issueDate;
        $this->currency        = $currency;
        $this->supplier        = $supplier;
        $this->customer        = $customer;
        $this->totals          = $totals;
        $this->customizationId = PeppolCustomizationIdentifier::fromString(PeppolBillingProfile::CUSTOMIZATION_ID);
        $this->profileId       = PeppolProcessIdentifier::fromString(PeppolBillingProfile::PROCESS_ID);
        $this->dates           = UblDraftDates::none();
        $this->references      = UblDraftReferences::none();
        $this->payment         = UblDraftPayment::none();
    }

    /**
     * Returns a copy with an updated issue date.
     *
     * @param LocalDate $issueDate New issue date.
     */
    public function withIssueDate(LocalDate $issueDate): self
    {
        $draft            = $this->copy();
        $draft->issueDate = $issueDate;

        return $draft;
    }

    /**
     * cbc:InvoiceTypeCode / cbc:CreditNoteTypeCode, see CodeList\DocumentTypeCode (default 380 / 381).
     *
     * @param UblDocumentTypeCode $typeCode Invoice or credit note type code written to the corresponding UBL root element.
     */
    public function withTypeCode(UblDocumentTypeCode $typeCode): self
    {
        $draft           = $this->copy();
        $draft->typeCode = $typeCode;

        return $draft;
    }

    /**
     * Sets the identifiers of the specification and business process followed by the document.
     * Defaults to Peppol BIS Billing 3.0.
     *
     * @param PeppolCustomizationIdentifier $customizationId Identifier of the document specification and its rules.
     * @param PeppolProcessIdentifier       $profileId       Identifier of the business process or profile within that specification.
     */
    public function withSpecification(
        PeppolCustomizationIdentifier $customizationId,
        PeppolProcessIdentifier $profileId
    ): self {
        $draft                  = $this->copy();
        $draft->customizationId = $customizationId;
        $draft->profileId       = $profileId;

        return $draft;
    }

    /**
     * Returns a copy with the tax accounting currency code, or omits it when null.
     *
     * @param CurrencyCode|null $currencyCode Tax accounting currency.
     */
    public function withTaxCurrencyCode(?CurrencyCode $currencyCode): self
    {
        $draft                  = $this->copy();
        $draft->taxCurrencyCode = $currencyCode;

        return $draft;
    }

    /**
     * Returns a copy with the optional dates and document period replaced as a set.
     *
     * @param UblDraftDates $dates Due, tax point and delivery dates and the invoice period.
     */
    public function withDates(UblDraftDates $dates): self
    {
        $draft        = $this->copy();
        $draft->dates = $dates;

        return $draft;
    }

    /**
     * Returns a copy with the document references replaced as a set.
     *
     * @param UblDraftReferences $references Buyer, accounting, order, billing and document references.
     */
    public function withReferences(UblDraftReferences $references): self
    {
        $draft             = $this->copy();
        $draft->references = $references;

        return $draft;
    }

    /**
     * Returns a copy with the payment instructions replaced as a set.
     *
     * @param UblDraftPayment $payment Payment means, payment terms note and separate payee.
     */
    public function withPayment(UblDraftPayment $payment): self
    {
        $draft          = $this->copy();
        $draft->payment = $payment;

        return $draft;
    }

    /**
     * Returns a copy with one more free-text note about the invoice or credit note.
     *
     * Every call appends: earlier notes are kept, unlike UblDraftLine::withNote(), which replaces the line note.
     *
     * @param string $note Invoice note or explanatory text.
     */
    public function withAddedNote(string $note): self
    {
        $draft          = $this->copy();
        $draft->notes[] = $note;

        return $draft;
    }

    /**
     * Returns a copy with delivery details, or omits them when null.
     *
     * @param UblDraftDelivery|null $delivery Delivery details to set or clear.
     */
    public function withDelivery(?UblDraftDelivery $delivery): self
    {
        $draft           = $this->copy();
        $draft->delivery = $delivery;

        return $draft;
    }

    /**
     * Returns a copy with tax representative details, or omits them when null.
     *
     * @param UblDraftTaxRepresentative|null $taxRepresentative Tax representative to set or clear.
     */
    public function withTaxRepresentative(?UblDraftTaxRepresentative $taxRepresentative): self
    {
        $draft                    = $this->copy();
        $draft->taxRepresentative = $taxRepresentative;

        return $draft;
    }

    /**
     * Replaces the document-level allowances and charges.
     *
     * @param  UblDraftAllowanceCharge[] $allowanceCharges Adjustments to write at document level.
     * @throws InvalidInputException     when an item is not a UblDraftAllowanceCharge.
     */
    public function withAllowanceCharges(array $allowanceCharges): self
    {
        $draft                   = $this->copy();
        $draft->allowanceCharges = TypedArray::valuesOfType(
            $allowanceCharges,
            UblDraftAllowanceCharge::class,
            'Every document allowance or charge must be a UblDraftAllowanceCharge.'
        );

        return $draft;
    }

    /**
     * Sets the goods or services being invoiced or credited.
     * @param  UblDraftLine[]        $lines Invoice or credit note lines, each with its quantity, price and VAT category.
     * @throws InvalidInputException when an item is not a UblDraftLine.
     */
    public function withLines(array $lines): self
    {
        $draft        = $this->copy();
        $draft->lines = TypedArray::valuesOfType(
            $lines,
            UblDraftLine::class,
            'Every document line must be a UblDraftLine.'
        );

        return $draft;
    }

    /**
     * Returns the kind.
     *
     * @return UblDocumentRoot
     */
    public function kind(): UblDocumentRoot
    {
        return $this->kind;
    }

    /**
     * Whether the draft represents a credit note.
     *
     * @return bool
     */
    public function isCreditNote(): bool
    {
        return $this->kind->isCreditNote();
    }

    /**
     * Returns the type code.
     *
     * @return UblDocumentTypeCode
     */
    public function typeCode(): UblDocumentTypeCode
    {
        return $this->typeCode;
    }

    /**
     * Returns the customization ID.
     *
     * @return PeppolCustomizationIdentifier
     */
    public function customizationId(): PeppolCustomizationIdentifier
    {
        return $this->customizationId;
    }

    /**
     * Returns the profile ID.
     *
     * @return PeppolProcessIdentifier
     */
    public function profileId(): PeppolProcessIdentifier
    {
        return $this->profileId;
    }

    /**
     * Returns the number.
     *
     * @return UblDocumentNumber
     */
    public function number(): UblDocumentNumber
    {
        return $this->number;
    }

    /**
     * Returns the issue date.
     *
     * @return LocalDate
     */
    public function issueDate(): LocalDate
    {
        return $this->issueDate;
    }

    /** Returns the canonical document currency code. */
    public function currency(): CurrencyCode
    {
        return $this->currency;
    }

    /**
     * Returns the supplier.
     *
     * @return UblDraftParty
     */
    public function supplier(): UblDraftParty
    {
        return $this->supplier;
    }

    /**
     * Returns the customer.
     *
     * @return UblDraftParty
     */
    public function customer(): UblDraftParty
    {
        return $this->customer;
    }

    /**
     * Returns the totals.
     *
     * @return UblDraftTotals
     */
    public function totals(): UblDraftTotals
    {
        return $this->totals;
    }

    /** Returns the tax accounting currency code, or null when it is omitted. */
    public function taxCurrencyCode(): ?CurrencyCode
    {
        return $this->taxCurrencyCode;
    }

    /**
     * Returns the notes to write into the UBL document.
     * @return string[]
     */
    public function notes(): array
    {
        return $this->notes;
    }

    /** Returns delivery details, or null when they are omitted. */
    public function delivery(): ?UblDraftDelivery
    {
        return $this->delivery;
    }

    /** Returns tax representative details, or null when they are omitted. */
    public function taxRepresentative(): ?UblDraftTaxRepresentative
    {
        return $this->taxRepresentative;
    }

    /**
     * Returns document-level allowances and charges in the order supplied.
     *
     * @return UblDraftAllowanceCharge[]
     */
    public function allowanceCharges(): array
    {
        return $this->allowanceCharges;
    }

    /**
     * Returns the document lines in the draft.
     * @return UblDraftLine[]
     */
    public function lines(): array
    {
        return $this->lines;
    }

    /** Returns the optional dates and the document period. */
    public function dates(): UblDraftDates
    {
        return $this->dates;
    }

    /** Returns the buyer, accounting, order, billing and document references. */
    public function references(): UblDraftReferences
    {
        return $this->references;
    }

    /** Returns the payment means, payment terms note and separate payee. */
    public function payment(): UblDraftPayment
    {
        return $this->payment;
    }

    /** Starts a copy-on-write update without changing the current draft. */
    private function copy(): self
    {
        return clone $this;
    }
}
