<?php

namespace DreaM1\EfakturaConnector\Domain\Ubl;

use DreaM1\EfakturaConnector\Support\Exceptions\InvalidInputException;
use DreaM1\EfakturaConnector\Support\TypedArray;

/** Parties, lines and VAT breakdown of a UBL document. */
final class UblDocumentContent
{
    /** Serialized array key containing the UBL root document kind. */
    private const FIELD_DOCUMENT_KIND = 'documentKind';
    /** Serialized array key containing the supplier party model. */
    private const FIELD_SUPPLIER = 'supplier';
    /** Serialized array key containing the customer party model. */
    private const FIELD_CUSTOMER = 'customer';
    /** Serialized array key containing the document lines. */
    private const FIELD_LINES = 'lines';
    /** Serialized array key indicating that additional items were omitted. */
    private const FIELD_ITEMS_TRUNCATED = 'itemsTruncated';
    /** Serialized array key containing tax summary entries. */
    private const FIELD_TAX_SUMMARY = 'taxSummary';

    private UblDocumentRoot $documentRoot;
    private UblDocumentParty $supplier;
    private UblDocumentParty $customer;
    /** @var list<UblDocumentLine> */
    private array $lines;
    private bool $itemsTruncated;
    /** @var list<UblDocumentTaxSummary> */
    private array $taxSummary;

    /**
     * @param UblDocumentLine[]       $lines      Invoice or credit note lines to process.
     * @param UblDocumentTaxSummary[] $taxSummary Tax totals and categories for the document.
     */
    private function __construct(
        UblDocumentRoot $documentRoot,
        UblDocumentParty $supplier,
        UblDocumentParty $customer,
        array $lines,
        bool $itemsTruncated,
        array $taxSummary
    ) {
        $this->documentRoot = $documentRoot;
        $this->supplier     = $supplier;
        $this->customer     = $customer;
        $this->lines        = TypedArray::valuesOfType(
            $lines,
            UblDocumentLine::class,
            'Every document line must be a UblDocumentLine.'
        );
        $this->itemsTruncated = $itemsTruncated;
        $this->taxSummary     = TypedArray::valuesOfType(
            $taxSummary,
            UblDocumentTaxSummary::class,
            'Every tax summary entry must be a UblDocumentTaxSummary.'
        );
    }

    /**
     * Creates parsed content from the document parties, lines and VAT subtotals.
     * @param  UblDocumentLine[]       $lines          Invoice or credit note lines parsed from the document.
     * @param  UblDocumentTaxSummary[] $taxSummary     Tax totals and categories for the document.
     * @param  string                  $documentKind   UBL document kind, such as Invoice or CreditNote.
     * @param  UblDocumentParty        $supplier       Supplier party data read from the document.
     * @param  UblDocumentParty        $customer       Customer party data read from the document.
     * @param  bool                    $itemsTruncated Whether additional items were omitted from the result.
     * @return self
     * @throws InvalidInputException   when the document root or a nested item has an unexpected value or type.
     */
    public static function create(
        string $documentKind,
        UblDocumentParty $supplier,
        UblDocumentParty $customer,
        array $lines,
        bool $itemsTruncated,
        array $taxSummary
    ): self {
        return new self(
            UblDocumentRoot::fromString($documentKind),
            $supplier,
            $customer,
            $lines,
            $itemsTruncated,
            $taxSummary
        );
    }

    /**
     * Restores content from toArray(); malformed optional nested values are left out instead of failing.
     * A missing or unsupported document kind is rejected because it is required for this value object.
     *
     * @param  array<string, mixed>  $details Serialized content fields produced by toArray().
     * @return self
     * @throws InvalidInputException when the document kind is missing or unsupported.
     */
    public static function fromArray(array $details): self
    {
        $lines = [];

        foreach (UblArrayValue::array($details, self::FIELD_LINES) as $line) {
            if (is_array($line)) {
                $lines[] = UblDocumentLine::fromArray($line);
            }
        }

        $taxSummary = [];

        foreach (UblArrayValue::array($details, self::FIELD_TAX_SUMMARY) as $tax) {
            if (is_array($tax)) {
                $taxSummary[] = UblDocumentTaxSummary::fromArray($tax);
            }
        }

        $documentKind = UblArrayValue::optionalString($details, self::FIELD_DOCUMENT_KIND);

        if ($documentKind === null) {
            throw new InvalidInputException('Serialized UBL content must contain a supported document kind.');
        }

        return new self(
            UblDocumentRoot::fromString($documentKind),
            UblDocumentParty::fromArray(UblArrayValue::array($details, self::FIELD_SUPPLIER)),
            UblDocumentParty::fromArray(UblArrayValue::array($details, self::FIELD_CUSTOMER)),
            $lines,
            UblArrayValue::boolean($details, self::FIELD_ITEMS_TRUNCATED),
            $taxSummary
        );
    }

    /**
     * Local name of the root element, see UblDocumentRoot.
     *
     * @return string
     */
    public function documentKind(): string
    {
        return $this->documentRoot->value();
    }

    /**
     * Returns the supplier.
     *
     * @return UblDocumentParty
     */
    public function supplier(): UblDocumentParty
    {
        return $this->supplier;
    }

    /**
     * Returns the customer.
     *
     * @return UblDocumentParty
     */
    public function customer(): UblDocumentParty
    {
        return $this->customer;
    }

    /**
     * Returns the UBL invoice or credit note lines.
     * @return UblDocumentLine[]
     */
    public function lines(): array
    {
        return $this->lines;
    }

    /**
     * Whether the supplied line list was already truncated before it became this value.
     *
     * @return bool
     */
    public function itemsTruncated(): bool
    {
        return $this->itemsTruncated;
    }

    /**
     * Returns the VAT subtotals read from the UBL document.
     * @return UblDocumentTaxSummary[]
     */
    public function taxSummary(): array
    {
        return $this->taxSummary;
    }

    /**
     * Whether the document contains a VAT subtotal for the supplied category.
     *
     * @param  string $category Category assigned to this value or validation finding.
     * @return bool
     */
    public function hasTaxCategory(string $category): bool
    {
        foreach ($this->taxSummary as $taxSummary) {
            if ($taxSummary->category() === $category) {
                return true;
            }
        }

        return false;
    }

    /**
     * Returns document content as named fields.
     *
     * @return array{documentKind: string, supplier: array<string, mixed>, customer: array<string, mixed>, lines: list<array<string, mixed>>, itemsTruncated: bool, taxSummary: list<array{category: string, rate: string, taxableAmount: string, taxAmount: string}>}
     */
    public function toArray(): array
    {
        return [
            self::FIELD_DOCUMENT_KIND => $this->documentRoot->value(),
            self::FIELD_SUPPLIER      => $this->supplier->toArray(),
            self::FIELD_CUSTOMER      => $this->customer->toArray(),
            self::FIELD_LINES         => array_map(
                static fn (UblDocumentLine $line): array => $line->toArray(),
                $this->lines
            ),
            self::FIELD_ITEMS_TRUNCATED => $this->itemsTruncated,
            self::FIELD_TAX_SUMMARY     => array_map(
                static fn (UblDocumentTaxSummary $taxSummary): array => $taxSummary->toArray(),
                $this->taxSummary
            ),
        ];
    }
}
