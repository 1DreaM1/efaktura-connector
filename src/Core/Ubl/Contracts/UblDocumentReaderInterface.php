<?php

namespace DreaM1\EfakturaConnector\Core\Ubl\Contracts;

use DreaM1\EfakturaConnector\Core\Ubl\Exceptions\UblValidationException;
use DreaM1\EfakturaConnector\Core\Ubl\UblXmlDocument;
use DreaM1\EfakturaConnector\Domain\Ubl\UblDocument;
use DreaM1\EfakturaConnector\Support\Exceptions\InvalidInputException;

/**
 * Reads the data of a UBL Invoice or CreditNote.
 *
 * Reading never enforces Peppol or EN 16931 rules, so received and older documents stay readable;
 * see PeppolBillingRulesInterface for the rules of an outgoing document.
 */
interface UblDocumentReaderInterface
{
    /**
     * Reads UBL Invoice or CreditNote XML without applying business-rule validation.
     *
     * @param  string                 $xml UBL Invoice or CreditNote XML to read into domain values.
     * @return UblDocument
     * @throws UblValidationException when the XML is unsafe, malformed or not a UBL Invoice or CreditNote
     * @throws InvalidInputException  when an identifier in the parsed document is invalid
     */
    public function read(string $xml): UblDocument;

    /**
     * Reads a document that was already loaded by UblXmlDocumentLoaderInterface.
     *
     * @param  UblXmlDocument        $document Parsed UBL document to read into domain values.
     * @return UblDocument
     * @throws InvalidInputException when an identifier in the parsed document is invalid
     */
    public function readDocument(UblXmlDocument $document): UblDocument;
}
