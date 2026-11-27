<?php

namespace DreaM1\EfakturaConnector\Infrastructure\Ubl\Writer;

use DOMElement;
use DOMException;
use DreaM1\EfakturaConnector\Core\Ubl\UblAttributeName;
use DreaM1\EfakturaConnector\Core\Ubl\UblElementName;
use DreaM1\EfakturaConnector\Domain\Ubl\CodeList\AdditionalDocumentTypeCode;
use DreaM1\EfakturaConnector\Domain\Ubl\Draft\UblDocumentDraft;
use DreaM1\EfakturaConnector\Domain\Ubl\Draft\UblDraftCommodityClassification;
use DreaM1\EfakturaConnector\Domain\Ubl\Draft\UblDraftIdentifier;
use DreaM1\EfakturaConnector\Domain\Ubl\Draft\UblDraftLine;
use DreaM1\EfakturaConnector\Infrastructure\Ubl\UblDocumentType;

/** Writes invoice and credit note line aggregates. */
final class UblLineWriter
{
    /**
     * Writes line details in UBL sequence: references and allowances, item, then price.
     *
     * @param  DOMElement       $root  UBL document root receiving the line.
     * @param  UblDocumentDraft $draft Parent document values, including currency.
     * @param  UblDraftLine     $line  Line values to serialize.
     * @param  UblDocumentType  $type  Document kind determining line and quantity elements.
     * @throws DOMException     when a UBL element cannot be created
     */
    public static function appendLine(
        DOMElement $root,
        UblDocumentDraft $draft,
        UblDraftLine $line,
        UblDocumentType $type
    ): void {
        $currency = $draft->currency()->value();

        // Create InvoiceLine or CreditNoteLine and write its ID, optional Note, kind-specific quantity, and net amount.
        $element = UblElementWriter::appendCac($root, $type->lineElement());
        UblElementWriter::appendDraftIdentifier($element, UblElementName::ID, $line->id());
        UblElementWriter::appendOptionalCbc($element, UblElementName::NOTE, $line->note());

        // Invoice and CreditNote use different quantity element names, but both attach the same unitCode attribute.
        UblElementWriter::appendDecimalCbc($element, $type->quantityElement(), $line->quantity())
            ->setAttribute(UblAttributeName::UNIT_CODE, $line->unitCode()->value());
        UblElementWriter::appendDecimalAmount(
            $element,
            UblElementName::LINE_EXTENSION_AMOUNT,
            $line->lineExtensionAmount(),
            $currency
        );
        UblElementWriter::appendOptionalCbc($element, UblElementName::ACCOUNTING_COST, $line->accountingCost());

        self::appendLineReferences($element, $line);

        foreach ($line->allowanceCharges() as $allowanceCharge) {
            // Line-level discounts and charges are written as AllowanceCharge aggregates under the line.
            UblAllowanceChargeWriter::append($element, $allowanceCharge, $currency, null);
        }

        // The remaining sequence is Item then Price; moving either breaks the line's schema order.
        self::appendLineItem($element, $line);
        self::appendLinePrice($element, $draft, $line);
    }

    /**
     * Writes the optional line period, order line reference and document reference.
     *
     * @throws DOMException when a UBL element cannot be created
     */
    private static function appendLineReferences(DOMElement $element, UblDraftLine $line): void
    {
        if ($line->period() !== null) {
            // The line period is InvoicePeriod/StartDate, EndDate, and optional DescriptionCode.
            UblPeriodWriter::append($element, UblElementName::INVOICE_PERIOD, $line->period());
        }

        if ($line->orderLineReference() !== null) {
            // OrderLineReference/LineID identifies the corresponding line in the buyer's order.
            $orderLine = UblElementWriter::appendCac($element, UblElementName::ORDER_LINE_REFERENCE);
            UblElementWriter::appendDraftIdentifier($orderLine, UblElementName::LINE_ID, $line->orderLineReference());
        }

        if ($line->documentReference() !== null) {
            // DocumentReference contains the referenced ID and an InvoicedObject document type code.
            $reference = UblElementWriter::appendCac($element, UblElementName::DOCUMENT_REFERENCE);
            UblElementWriter::appendDraftIdentifier($reference, UblElementName::ID, $line->documentReference());
            UblElementWriter::appendCbc(
                $reference,
                UblElementName::DOCUMENT_TYPE_CODE,
                AdditionalDocumentTypeCode::INVOICED_OBJECT
            );
        }
    }

    /**
     * Writes Item children in schema order, with identifiers and classifications before its tax category.
     *
     * @throws DOMException when the DOM rejects an XML element or attribute operation.
     */
    private static function appendLineItem(DOMElement $element, UblDraftLine $line): void
    {
        // Item contains its description/name, buyer/seller/standard identifiers, origin and classifications.
        $item = UblElementWriter::appendCac($element, UblElementName::ITEM);
        UblElementWriter::appendOptionalCbc($item, UblElementName::DESCRIPTION, $line->itemDescription());
        UblElementWriter::appendCbc($item, UblElementName::NAME, $line->itemName());
        self::appendItemIdentifier($item, UblElementName::BUYERS_ITEM_IDENTIFICATION, $line->buyersItemIdentifier());
        self::appendItemIdentifier($item, UblElementName::SELLERS_ITEM_IDENTIFICATION, $line->sellersItemIdentifier());
        self::appendItemIdentifier(
            $item,
            UblElementName::STANDARD_ITEM_IDENTIFICATION,
            $line->standardItemIdentifier()
        );

        if ($line->originCountryCode() !== null) {
            $origin = UblElementWriter::appendCac($item, UblElementName::ORIGIN_COUNTRY);
            UblElementWriter::appendCbc(
                $origin,
                UblElementName::IDENTIFICATION_CODE,
                $line->originCountryCode()->value()
            );
        }

        foreach ($line->commodityClassifications() as $classification) {
            self::appendCommodityClassification($item, $classification);
        }

        UblTaxCategoryWriter::appendTaxCategory($item, UblElementName::CLASSIFIED_TAX_CATEGORY, $line->taxCategory());

        foreach ($line->itemProperties() as $property) {
            // Each AdditionalItemProperty writes a Name and Value pair.
            $additionalProperty = UblElementWriter::appendCac($item, UblElementName::ADDITIONAL_ITEM_PROPERTY);
            UblElementWriter::appendCbc($additionalProperty, UblElementName::NAME, $property->name());
            UblElementWriter::appendCbc($additionalProperty, UblElementName::VALUE, $property->value());
        }
    }

    /**
     * Writes one CommodityClassification with its ItemClassificationCode, listID and optional listVersionID.
     *
     * @throws DOMException when a UBL element cannot be created
     */
    private static function appendCommodityClassification(
        DOMElement $item,
        UblDraftCommodityClassification $classification
    ): void {
        $commodity = UblElementWriter::appendCac($item, UblElementName::COMMODITY_CLASSIFICATION);
        $code      = UblElementWriter::appendCbc(
            $commodity,
            UblElementName::ITEM_CLASSIFICATION_CODE,
            $classification->code()
        );
        UblElementWriter::setAttribute($code, UblAttributeName::LIST_ID, $classification->listId());
        UblElementWriter::setAttributeIfPresent($code, UblAttributeName::LIST_VERSION_ID, $classification->listVersionId());
    }

    /** @throws DOMException */
    private static function appendLinePrice(DOMElement $element, UblDocumentDraft $draft, UblDraftLine $line): void
    {
        // Price contains PriceAmount, optional BaseQuantity, and any price-level AllowanceCharge.
        $price = UblElementWriter::appendCac($element, UblElementName::PRICE);
        UblElementWriter::appendDecimalAmount(
            $price,
            UblElementName::PRICE_AMOUNT,
            $line->priceAmount(),
            $draft->currency()->value()
        );

        if ($line->priceBaseQuantity() !== null) {
            // unitCode is meaningful only when a base quantity is present.
            $baseQuantity = UblElementWriter::appendDecimalCbc(
                $price,
                UblElementName::BASE_QUANTITY,
                $line->priceBaseQuantity()
            );
            UblElementWriter::setAttributeIfPresent(
                $baseQuantity,
                UblAttributeName::UNIT_CODE,
                $line->priceBaseUnitCode() !== null ? $line->priceBaseUnitCode()->value() : null
            );
        }

        if ($line->priceAllowance() !== null) {
            UblAllowanceChargeWriter::appendPriceAllowance(
                $price,
                $line->priceAllowance(),
                $draft->currency()->value()
            );
        }
    }

    /** @throws DOMException */
    private static function appendItemIdentifier(
        DOMElement $item,
        string $elementName,
        ?UblDraftIdentifier $identifier
    ): void {
        if ($identifier === null) {
            return;
        }

        $aggregate = UblElementWriter::appendCac($item, $elementName);
        UblElementWriter::appendDraftIdentifier($aggregate, UblElementName::ID, $identifier);
    }
}
