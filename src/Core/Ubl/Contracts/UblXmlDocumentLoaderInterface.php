<?php

namespace DreaM1\EfakturaConnector\Core\Ubl\Contracts;

use DreaM1\EfakturaConnector\Core\Ubl\Exceptions\UblValidationException;
use DreaM1\EfakturaConnector\Core\Ubl\UblXmlDocument;

/** Securely parses XML and verifies that its root is a supported UBL document type. */
interface UblXmlDocumentLoaderInterface
{
    /**
     * Parses XML and verifies that it is a supported UBL document type.
     *
     * @param  string                 $xml XML text to parse as a supported UBL Invoice or CreditNote.
     * @return UblXmlDocument
     * @throws UblValidationException for unsafe, malformed or unsupported XML
     */
    public function parse(string $xml): UblXmlDocument;
}
