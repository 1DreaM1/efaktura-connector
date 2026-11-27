<?php

namespace DreaM1\EfakturaConnector\Domain\Ubl\Draft;

use DreaM1\EfakturaConnector\Domain\Ubl\Code\CountryCode;
use DreaM1\EfakturaConnector\Domain\Ubl\Code\UnitCode;
use DreaM1\EfakturaConnector\Support\Exceptions\InvalidInputException;
use DreaM1\EfakturaConnector\Support\TypedArray;

/** cac:InvoiceLine or cac:CreditNoteLine; quantities and amounts are UBL decimals. */
final class UblDraftLine
{
    /** Line identifier written to cbc:ID. */
    private UblDraftIdentifier $id;
    /** Invoiced or credited quantity, written to the root-specific quantity element. */
    private UblDecimal $quantity;
    /** Quantity unit written as the quantity element's unitCode attribute. */
    private UnitCode $unitCode;
    /** Net line total after line allowances/charges, written to cbc:LineExtensionAmount. */
    private UblDecimal $lineExtensionAmount;
    /** Item name written to cac:Item/cbc:Name. */
    private string $itemName;
    /** VAT category and rate written to cac:Item/cac:ClassifiedTaxCategory. */
    private UblDraftTaxCategory $taxCategory;
    /** Price per item or service unit, written to cac:Price/cbc:PriceAmount. */
    private UblDecimal $priceAmount;
    /** Optional free-text line note written to cbc:Note. */
    private ?string $note = null;
    /** Optional line accounting code written to cbc:AccountingCost. */
    private ?string $accountingCost = null;
    /** Optional period covered by this line, written to cac:InvoicePeriod. */
    private ?UblDraftPeriod $period = null;
    /** Buyer's related order-line ID, written to cac:OrderLineReference/cbc:LineID. */
    private ?UblDraftIdentifier $orderLineReference = null;
    /** Related document ID, written to cac:DocumentReference/cbc:ID. */
    private ?UblDraftIdentifier $documentReference = null;
    /** @var UblDraftAllowanceCharge[] Line-level discounts and charges written as cac:AllowanceCharge elements. */
    private array $allowanceCharges = [];
    /** Optional longer item description, written to cac:Item/cbc:Description. */
    private ?string $itemDescription = null;
    /** Buyer's item ID, written to cac:Item/cac:BuyersItemIdentification/cbc:ID. */
    private ?UblDraftIdentifier $buyersItemIdentifier = null;
    /** Seller's item ID, written to cac:Item/cac:SellersItemIdentification/cbc:ID. */
    private ?UblDraftIdentifier $sellersItemIdentifier = null;
    /** Standard item ID, written to cac:Item/cac:StandardItemIdentification/cbc:ID. */
    private ?UblDraftIdentifier $standardItemIdentifier = null;
    /** Item's origin country, written to cac:Item/cac:OriginCountry/cbc:IdentificationCode. */
    private ?CountryCode $originCountryCode = null;
    /** @var UblDraftCommodityClassification[] Item classification codes written under cac:Item/cac:CommodityClassification. */
    private array $commodityClassifications = [];
    /** @var UblDraftItemProperty[] Name/value pairs written under cac:Item/cac:AdditionalItemProperty. */
    private array $itemProperties = [];
    /** Optional quantity used as the basis for PriceAmount, written to cac:Price/cbc:BaseQuantity. */
    private ?UblDecimal $priceBaseQuantity = null;
    /** Unit for the price base quantity, written as BaseQuantity's unitCode attribute. */
    private ?UnitCode $priceBaseUnitCode = null;
    /** Optional discount applied to PriceAmount, written as cac:Price/cac:AllowanceCharge. */
    private ?UblDraftPriceAllowance $priceAllowance = null;

    /**
     * Creates a new UblDraftLine value.
     * @param UnitCode            $unitCode            UN/ECE Recommendation 20 code.
     * @param UblDraftIdentifier  $id                  Identifier assigned to this invoice or credit note line.
     * @param UblDecimal          $quantity            Quantity of goods or services invoiced on this line.
     * @param UblDecimal          $lineExtensionAmount Line total including line allowances or charges, net of tax.
     * @param string              $itemName            Name of the invoiced good or service.
     * @param UblDraftTaxCategory $taxCategory         VAT category and rate applied to this line.
     * @param UblDecimal          $priceAmount         Item price written as cbc:PriceAmount.
     */
    public function __construct(
        UblDraftIdentifier $id,
        UblDecimal $quantity,
        UnitCode $unitCode,
        UblDecimal $lineExtensionAmount,
        string $itemName,
        UblDraftTaxCategory $taxCategory,
        UblDecimal $priceAmount
    ) {
        $this->unitCode            = $unitCode;
        $this->id                  = $id;
        $this->quantity            = $quantity;
        $this->lineExtensionAmount = $lineExtensionAmount;
        $this->itemName            = $itemName;
        $this->taxCategory         = $taxCategory;
        $this->priceAmount         = $priceAmount;
    }

    /**
     * Returns the ID.
     *
     * @return UblDraftIdentifier
     */
    public function id(): UblDraftIdentifier
    {
        return $this->id;
    }

    /**
     * Returns the quantity.
     *
     * @return UblDecimal
     */
    public function quantity(): UblDecimal
    {
        return $this->quantity;
    }

    /** Returns the typed unit code written to cbc:unitCode. */
    public function unitCode(): UnitCode
    {
        return $this->unitCode;
    }

    /**
     * Returns the line extension amount.
     *
     * @return UblDecimal
     */
    public function lineExtensionAmount(): UblDecimal
    {
        return $this->lineExtensionAmount;
    }

    /**
     * Returns the item name.
     *
     * @return string
     */
    public function itemName(): string
    {
        return $this->itemName;
    }

    /**
     * Returns the tax category.
     *
     * @return UblDraftTaxCategory
     */
    public function taxCategory(): UblDraftTaxCategory
    {
        return $this->taxCategory;
    }

    /**
     * Returns the price amount.
     *
     * @return UblDecimal
     */
    public function priceAmount(): UblDecimal
    {
        return $this->priceAmount;
    }

    /**
     * Returns a copy with the line note, clearing it when null or blank.
     *
     * @param string|null $note Free-text note for this document line.
     */
    public function withNote(?string $note): self
    {
        $line       = clone $this;
        $line->note = UblDraftText::emptyToNull($note);

        return $line;
    }

    /**
     * Returns a copy with the line accounting cost identifier, clearing it when null or blank.
     *
     * @param string|null $accountingCost Accounting cost identifier for this line.
     */
    public function withAccountingCost(?string $accountingCost): self
    {
        $line                 = clone $this;
        $line->accountingCost = UblDraftText::emptyToNull($accountingCost);

        return $line;
    }

    /**
     * Returns a copy with the period covered by this document line, or clears it when null.
     *
     * @param UblDraftPeriod|null $period Period covered by this line.
     */
    public function withPeriod(?UblDraftPeriod $period): self
    {
        $line         = clone $this;
        $line->period = $period;

        return $line;
    }

    /**
     * Returns a copy with the buyer's referenced order line ID, clearing it when null or blank.
     *
     * @param UblDraftIdentifier|null $lineId Identifier of the corresponding buyer order line.
     */
    public function withOrderLineReference(?UblDraftIdentifier $lineId): self
    {
        $line                     = clone $this;
        $line->orderLineReference = $lineId;

        return $line;
    }

    /**
     * Returns a copy with the document reference associated with this line, or clears it when null.
     *
     * @param UblDraftIdentifier|null $identifier Document identifier to associate with this line.
     */
    public function withDocumentReference(?UblDraftIdentifier $identifier): self
    {
        $line                    = clone $this;
        $line->documentReference = $identifier;

        return $line;
    }

    /**
     * Returns a copy with the complete line-level allowance and charge list.
     * The supplied list replaces existing adjustments and is reindexed from zero.
     *
     * @param  UblDraftAllowanceCharge[] $allowanceCharges Line-level adjustments in serialization order.
     * @throws InvalidInputException     when an item is not a UblDraftAllowanceCharge.
     */
    public function withAllowanceCharges(array $allowanceCharges): self
    {
        $line                   = clone $this;
        $line->allowanceCharges = TypedArray::valuesOfType(
            $allowanceCharges,
            UblDraftAllowanceCharge::class,
            'Every line allowance or charge must be a UblDraftAllowanceCharge.'
        );

        return $line;
    }

    /**
     * Returns a copy with the item's longer description, clearing it when null or blank.
     *
     * @param string|null $description Longer description of the invoiced item.
     */
    public function withItemDescription(?string $description): self
    {
        $line                  = clone $this;
        $line->itemDescription = UblDraftText::emptyToNull($description);

        return $line;
    }

    /**
     * Returns a copy with any supplied buyer, seller and standard item identifiers.
     * Omitted arguments clear their corresponding identifier.
     *
     * @param UblDraftIdentifier|null $buyer    Buyer's item identifier.
     * @param UblDraftIdentifier|null $seller   Seller's item identifier.
     * @param UblDraftIdentifier|null $standard Standard item identifier.
     */
    public function withItemIdentifiers(
        ?UblDraftIdentifier $buyer = null,
        ?UblDraftIdentifier $seller = null,
        ?UblDraftIdentifier $standard = null
    ): self {
        $line                         = clone $this;
        $line->buyersItemIdentifier   = $buyer;
        $line->sellersItemIdentifier  = $seller;
        $line->standardItemIdentifier = $standard;

        return $line;
    }

    /**
     * Returns a copy with the item's country of origin, or clears it when null.
     *
     * @param CountryCode|null $countryCode Country where the item originated.
     */
    public function withOriginCountryCode(?CountryCode $countryCode): self
    {
        $line                    = clone $this;
        $line->originCountryCode = $countryCode;

        return $line;
    }

    /**
     * Returns a copy with the complete item commodity classification list.
     *
     * @param  UblDraftCommodityClassification[] $classifications Classifications in serialization order.
     * @throws InvalidInputException             when an item is not a UblDraftCommodityClassification.
     */
    public function withCommodityClassifications(array $classifications): self
    {
        $line                           = clone $this;
        $line->commodityClassifications = TypedArray::valuesOfType(
            $classifications,
            UblDraftCommodityClassification::class,
            'Every commodity classification must be a UblDraftCommodityClassification.'
        );

        return $line;
    }

    /**
     * Returns a copy with the complete set of additional item properties.
     *
     * @param  UblDraftItemProperty[] $properties Item properties in serialization order.
     * @throws InvalidInputException  when an item is not a UblDraftItemProperty.
     */
    public function withItemProperties(array $properties): self
    {
        $line                 = clone $this;
        $line->itemProperties = TypedArray::valuesOfType(
            $properties,
            UblDraftItemProperty::class,
            'Every item property must be a UblDraftItemProperty.'
        );

        return $line;
    }

    /**
     * Returns a copy with the quantity and optional unit used as the item price base.
     * Passing null for the quantity omits the price base quantity and unit.
     *
     * @param UblDecimal|null $quantity Quantity on which the item price is based.
     * @param UnitCode|null   $unitCode Unit for the price base quantity.
     */
    public function withPriceBaseQuantity(?UblDecimal $quantity, ?UnitCode $unitCode = null): self
    {
        $line                    = clone $this;
        $line->priceBaseQuantity = $quantity;
        $line->priceBaseUnitCode = $unitCode;

        return $line;
    }

    /**
     * Returns a copy with the item price allowance, or clears it when null.
     *
     * @param UblDraftPriceAllowance|null $allowance Price-level allowance to set or clear.
     */
    public function withPriceAllowance(?UblDraftPriceAllowance $allowance): self
    {
        $line                 = clone $this;
        $line->priceAllowance = $allowance;

        return $line;
    }

    /** Returns the line note, or null when it is omitted. */
    public function note(): ?string
    {
        return $this->note;
    }

    /** Returns the line accounting cost identifier, or null when it is omitted. */
    public function accountingCost(): ?string
    {
        return $this->accountingCost;
    }

    /** Returns the period covered by this line, or null when it is omitted. */
    public function period(): ?UblDraftPeriod
    {
        return $this->period;
    }

    /** Returns the buyer's referenced order line ID, or null when it is omitted. */
    public function orderLineReference(): ?UblDraftIdentifier
    {
        return $this->orderLineReference;
    }

    /** Returns the document reference associated with this line, or null when it is omitted. */
    public function documentReference(): ?UblDraftIdentifier
    {
        return $this->documentReference;
    }

    /**
     * Returns the line-level allowances and charges in the order supplied.
     *
     * @return UblDraftAllowanceCharge[]
     */
    public function allowanceCharges(): array
    {
        return $this->allowanceCharges;
    }

    /** Returns the longer item description, or null when it is omitted. */
    public function itemDescription(): ?string
    {
        return $this->itemDescription;
    }

    /** Returns the buyer's item identifier, or null when it is omitted. */
    public function buyersItemIdentifier(): ?UblDraftIdentifier
    {
        return $this->buyersItemIdentifier;
    }

    /** Returns the seller's item identifier, or null when it is omitted. */
    public function sellersItemIdentifier(): ?UblDraftIdentifier
    {
        return $this->sellersItemIdentifier;
    }

    /** Returns the standard item identifier, or null when it is omitted. */
    public function standardItemIdentifier(): ?UblDraftIdentifier
    {
        return $this->standardItemIdentifier;
    }

    /** Returns the item's country of origin, or null when it is omitted. */
    public function originCountryCode(): ?CountryCode
    {
        return $this->originCountryCode;
    }

    /**
     * Returns item commodity classifications in the order supplied.
     *
     * @return UblDraftCommodityClassification[]
     */
    public function commodityClassifications(): array
    {
        return $this->commodityClassifications;
    }

    /**
     * Returns additional item properties in the order supplied.
     *
     * @return UblDraftItemProperty[]
     */
    public function itemProperties(): array
    {
        return $this->itemProperties;
    }

    /** Returns the quantity used as the item price base, or null when it is omitted. */
    public function priceBaseQuantity(): ?UblDecimal
    {
        return $this->priceBaseQuantity;
    }

    /** Returns the unit for the price base quantity, or null when it is omitted. */
    public function priceBaseUnitCode(): ?UnitCode
    {
        return $this->priceBaseUnitCode;
    }

    /** Returns the item price allowance, or null when it is omitted. */
    public function priceAllowance(): ?UblDraftPriceAllowance
    {
        return $this->priceAllowance;
    }
}
