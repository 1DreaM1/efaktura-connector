<?php

namespace DreaM1\EfakturaConnector\Domain\Ubl;

use DreaM1\EfakturaConnector\Domain\Ubl\Code\CurrencyCode;
use DreaM1\EfakturaConnector\Domain\Ubl\Code\UnitCode;

/** One cac:InvoiceLine or cac:CreditNoteLine; every value is the text of the document or null when it is missing. */
final class UblDocumentLine
{
    /** Serialized array key containing the line identifier. */
    private const FIELD_ID = 'id';
    /** Serialized array key containing the item name. */
    private const FIELD_NAME = 'name';
    /** Serialized array key containing the invoiced quantity. */
    private const FIELD_QUANTITY = 'quantity';
    /** Serialized array key containing the unit of measure. */
    private const FIELD_UNIT = 'unit';
    /** Serialized array key containing the unit price. */
    private const FIELD_UNIT_PRICE = 'unitPrice';
    /** Serialized array key containing the line total. */
    private const FIELD_LINE_TOTAL = 'lineTotal';
    /** Serialized array key containing the currency code. */
    private const FIELD_CURRENCY = 'currency';
    /** Serialized array key containing the VAT rate. */
    private const FIELD_VAT_RATE = 'vatRate';
    /** Serialized array key containing the VAT category code. */
    private const FIELD_VAT_CATEGORY = 'vatCategory';

    private ?string $id;
    private ?string $name;
    private UblDocumentLineQuantity $quantity;
    private UblDocumentLineAmounts $amounts;
    private UblDocumentLineVat $vat;

    /** Restricts construction to factories that map parsed UBL line values. */
    private function __construct(
        ?string $id,
        ?string $name,
        UblDocumentLineQuantity $quantity,
        UblDocumentLineAmounts $amounts,
        UblDocumentLineVat $vat
    ) {
        $this->id       = $id;
        $this->name     = $name;
        $this->quantity = $quantity;
        $this->amounts  = $amounts;
        $this->vat      = $vat;
    }

    /**
     * Creates a value from its parts.
     *
     * @param  string|null             $id       Identifier assigned to the invoice or credit note line.
     * @param  string|null             $name     Name of the invoiced good or service.
     * @param  UblDocumentLineQuantity $quantity Quantity invoiced on the line and its unit of measure.
     * @param  UblDocumentLineAmounts  $amounts  Unit price, line total and their currency.
     * @param  UblDocumentLineVat      $vat      VAT rate and category applied to the line.
     * @return self
     */
    public static function fromValues(
        ?string $id,
        ?string $name,
        UblDocumentLineQuantity $quantity,
        UblDocumentLineAmounts $amounts,
        UblDocumentLineVat $vat
    ): self {
        return new self($id, $name, $quantity, $amounts, $vat);
    }

    /**
     * Restores a UBL line from its array representation.
     *
     * @param  array<string, mixed> $line the form produced by toArray()
     * @return self
     */
    public static function fromArray(array $line): self
    {
        return self::fromValues(
            UblArrayValue::optionalString($line, self::FIELD_ID),
            UblArrayValue::optionalString($line, self::FIELD_NAME),
            new UblDocumentLineQuantity(
                UblArrayValue::optionalString($line, self::FIELD_QUANTITY),
                UblArrayValue::optionalString($line, self::FIELD_UNIT)
            ),
            new UblDocumentLineAmounts(
                UblArrayValue::optionalString($line, self::FIELD_UNIT_PRICE),
                UblArrayValue::optionalString($line, self::FIELD_LINE_TOTAL),
                UblArrayValue::optionalString($line, self::FIELD_CURRENCY)
            ),
            new UblDocumentLineVat(
                UblArrayValue::optionalString($line, self::FIELD_VAT_RATE),
                UblArrayValue::optionalString($line, self::FIELD_VAT_CATEGORY)
            )
        );
    }

    /**
     * Returns the ID.
     *
     * @return string|null
     */
    public function id(): ?string
    {
        return $this->id;
    }

    /**
     * Returns the item name, or its description when the source line has no item name.
     *
     * @return string|null
     */
    public function name(): ?string
    {
        return $this->name;
    }

    /** Returns the quantity and its unit of measure. */
    public function quantityDetails(): UblDocumentLineQuantity
    {
        return $this->quantity;
    }

    /** Returns the unit price, line total and currency. */
    public function amounts(): UblDocumentLineAmounts
    {
        return $this->amounts;
    }

    /** Returns the VAT rate and category. */
    public function vat(): UblDocumentLineVat
    {
        return $this->vat;
    }

    /** Returns the quantity. */
    public function quantity(): ?string
    {
        return $this->quantity->quantity();
    }

    /** UN/ECE Recommendation 20 unit code (@unitCode). */
    public function unit(): ?string
    {
        return $this->quantity->unit();
    }

    /** Returns the recognized canonical unit while unit() preserves the original document text. */
    public function recognizedUnitCode(): ?UnitCode
    {
        return $this->quantity->recognizedUnitCode();
    }

    /** Returns the unit price. */
    public function unitPrice(): ?string
    {
        return $this->amounts->unitPrice();
    }

    /** Returns the line total. */
    public function lineTotal(): ?string
    {
        return $this->amounts->lineTotal();
    }

    /** Returns the currency as written in the document. */
    public function currency(): ?string
    {
        return $this->amounts->currency();
    }

    /** Returns the recognized canonical currency while currency() preserves the original document text. */
    public function recognizedCurrencyCode(): ?CurrencyCode
    {
        return $this->amounts->recognizedCurrencyCode();
    }

    /** Returns the VAT rate. */
    public function vatRate(): ?string
    {
        return $this->vat->rate();
    }

    /** Returns the VAT category. */
    public function vatCategory(): ?string
    {
        return $this->vat->category();
    }

    /**
     * Returns line values as named fields.
     *
     * @return array{id: string|null, name: string|null, quantity: string|null, unit: string|null, unitPrice: string|null, lineTotal: string|null, currency: string|null, vatRate: string|null, vatCategory: string|null}
     */
    public function toArray(): array
    {
        return [
            self::FIELD_ID           => $this->id,
            self::FIELD_NAME         => $this->name,
            self::FIELD_QUANTITY     => $this->quantity->quantity(),
            self::FIELD_UNIT         => $this->quantity->unit(),
            self::FIELD_UNIT_PRICE   => $this->amounts->unitPrice(),
            self::FIELD_LINE_TOTAL   => $this->amounts->lineTotal(),
            self::FIELD_CURRENCY     => $this->amounts->currency(),
            self::FIELD_VAT_RATE     => $this->vat->rate(),
            self::FIELD_VAT_CATEGORY => $this->vat->category(),
        ];
    }
}
