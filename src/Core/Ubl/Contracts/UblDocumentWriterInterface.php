<?php

namespace DreaM1\EfakturaConnector\Core\Ubl\Contracts;

use DOMException;
use DreaM1\EfakturaConnector\Domain\Ubl\Draft\UblDocumentDraft;
use DreaM1\EfakturaConnector\Support\Exceptions\InvalidInputException;

/** Writes a UBL 2.1 Invoice or CreditNote in the element order of the UBL schema. */
interface UblDocumentWriterInterface
{
    /**
     * Serializes the draft as UBL XML without running schema or business-rule validation.
     *
     * @param  UblDocumentDraft      $draft UBL document draft to serialize or submit.
     * @return string
     * @throws DOMException          if the XML document cannot be assembled
     * @throws InvalidInputException if a credit note has a due date but no payment means
     */
    public function write(UblDocumentDraft $draft): string;
}
