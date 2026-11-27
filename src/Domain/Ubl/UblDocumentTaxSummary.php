<?php

namespace DreaM1\EfakturaConnector\Domain\Ubl;

/** One cac:TaxSubtotal of the first cac:TaxTotal. */
final class UblDocumentTaxSummary
{
    /** Serialized array key containing the VAT category code. */
    private const FIELD_CATEGORY = 'category';
    /** Serialized array key containing the VAT rate. */
    private const FIELD_RATE = 'rate';
    /** Serialized array key containing the taxable amount. */
    private const FIELD_TAXABLE_AMOUNT = 'taxableAmount';
    /** Serialized array key containing the calculated tax amount. */
    private const FIELD_TAX_AMOUNT = 'taxAmount';

    private string $category;
    private string $rate;
    private string $taxableAmount;
    private string $taxAmount;

    /** Restricts construction to the tax summary value factory. */
    private function __construct(string $category, string $rate, string $taxableAmount, string $taxAmount)
    {
        $this->category      = $category;
        $this->rate          = $rate;
        $this->taxableAmount = $taxableAmount;
        $this->taxAmount     = $taxAmount;
    }

    /**
     * Creates a value from its individual fields.
     *
     * @param  string $category      Category assigned to this value or validation finding.
     * @param  string $rate          Rate used for the calculation.
     * @param  string $taxableAmount Taxable monetary base for the tax category.
     * @param  string $taxAmount     Tax amount calculated for the tax category.
     * @return self
     */
    public static function fromValues(string $category, string $rate, string $taxableAmount, string $taxAmount): self
    {
        return new self($category, $rate, $taxableAmount, $taxAmount);
    }

    /**
     * Restores a VAT subtotal from its array representation.
     * @param  array<string, mixed> $taxSummary the form produced by toArray()
     * @return self
     */
    public static function fromArray(array $taxSummary): self
    {
        return self::fromValues(
            (string) UblArrayValue::optionalString($taxSummary, self::FIELD_CATEGORY),
            (string) UblArrayValue::optionalString($taxSummary, self::FIELD_RATE),
            (string) UblArrayValue::optionalString($taxSummary, self::FIELD_TAXABLE_AMOUNT),
            (string) UblArrayValue::optionalString($taxSummary, self::FIELD_TAX_AMOUNT)
        );
    }

    /**
     * UNCL 5305 code, see CodeList\TaxCategoryCode.
     *
     * @return string
     */
    public function category(): string
    {
        return $this->category;
    }

    /**
     * Returns the rate.
     *
     * @return string
     */
    public function rate(): string
    {
        return $this->rate;
    }

    /**
     * Returns the taxable amount.
     *
     * @return string
     */
    public function taxableAmount(): string
    {
        return $this->taxableAmount;
    }

    /**
     * Returns the tax amount.
     *
     * @return string
     */
    public function taxAmount(): string
    {
        return $this->taxAmount;
    }

    /**
     * Returns VAT subtotal values as named fields.
     *
     * @return array{category: string, rate: string, taxableAmount: string, taxAmount: string}
     */
    public function toArray(): array
    {
        return [
            self::FIELD_CATEGORY       => $this->category,
            self::FIELD_RATE           => $this->rate,
            self::FIELD_TAXABLE_AMOUNT => $this->taxableAmount,
            self::FIELD_TAX_AMOUNT     => $this->taxAmount,
        ];
    }
}
