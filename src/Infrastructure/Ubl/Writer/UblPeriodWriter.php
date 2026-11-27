<?php

namespace DreaM1\EfakturaConnector\Infrastructure\Ubl\Writer;

use DOMElement;
use DOMException;
use DreaM1\EfakturaConnector\Core\Ubl\UblElementName;
use DreaM1\EfakturaConnector\Domain\Ubl\Draft\UblDraftPeriod;

/** Writes an invoice or line period aggregate. */
final class UblPeriodWriter
{
    /**
     * Appends a UBL period with any supplied start date, end date and description code.
     *
     * @param  DOMElement     $parent      Parent aggregate element.
     * @param  string         $elementName UBL period aggregate element name.
     * @param  UblDraftPeriod $period      Period values to serialize.
     * @throws DOMException   when a UBL element cannot be created
     */
    public static function append(DOMElement $parent, string $elementName, UblDraftPeriod $period): void
    {
        // InvoicePeriod (or another requested period aggregate) contains StartDate, EndDate, and optional DescriptionCode.
        $element = UblElementWriter::appendCac($parent, $elementName);
        UblElementWriter::appendOptionalDate($element, UblElementName::START_DATE, $period->startDate());
        UblElementWriter::appendOptionalDate($element, UblElementName::END_DATE, $period->endDate());
        $descriptionCode = $period->descriptionCode();
        UblElementWriter::appendOptionalCbc(
            $element,
            UblElementName::DESCRIPTION_CODE,
            $descriptionCode !== null ? $descriptionCode->value() : null
        );
    }
}
