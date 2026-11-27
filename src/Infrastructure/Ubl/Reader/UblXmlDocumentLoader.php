<?php

namespace DreaM1\EfakturaConnector\Infrastructure\Ubl\Reader;

use DreaM1\EfakturaConnector\Core\Ubl\Contracts\UblXmlDocumentLoaderInterface;
use DreaM1\EfakturaConnector\Core\Ubl\Exceptions\UblValidationException;
use DreaM1\EfakturaConnector\Core\Ubl\UblXmlDocument;

/**
 * Securely parses size-limited XML and accepts only UBL Invoice and CreditNote roots.
 *
 * @see https://docs.oasis-open.org/ubl/os-UBL-2.1/UBL-2.1.html
 */
class UblXmlDocumentLoader implements UblXmlDocumentLoaderInterface
{
    /**
     * Parses size-limited XML and verifies it is a supported UBL Invoice or CreditNote.
     *
     * @param  string                 $xml Raw XML text to check for a supported UBL Invoice or CreditNote root.
     * @return UblXmlDocument
     * @throws UblValidationException for unsafe, malformed or unsupported XML
     */
    public function parse(string $xml): UblXmlDocument
    {
        // The core factory enforces the XML size/safety checks and accepts only a UBL Invoice or CreditNote root.
        return UblXmlDocument::fromXml($xml);
    }
}
