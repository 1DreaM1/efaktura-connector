<?php

namespace DreaM1\EfakturaConnector\Infrastructure\Ubl\Reader;

use DreaM1\EfakturaConnector\Core\Ubl\Contracts\UblDocumentReaderInterface;
use DreaM1\EfakturaConnector\Core\Ubl\Contracts\UblXmlDocumentLoaderInterface;
use DreaM1\EfakturaConnector\Core\Ubl\Exceptions\UblValidationException;
use DreaM1\EfakturaConnector\Core\Ubl\UblXmlDocument;
use DreaM1\EfakturaConnector\Domain\Ubl\UblDocument;
use DreaM1\EfakturaConnector\Support\Exceptions\InvalidInputException;

/** Reads supported UBL XML into the package's header and content value objects. */
class UblDocumentReader implements UblDocumentReaderInterface
{
    private UblXmlDocumentLoaderInterface $documentLoader;
    private UblDocumentHeaderReader $headerReader;
    private UblDocumentContentReader $contentReader;

    /**
     * @param UblXmlDocumentLoaderInterface $documentLoader Loader used to parse UBL XML into a document value.
     */
    public function __construct(UblXmlDocumentLoaderInterface $documentLoader)
    {
        $this->documentLoader = $documentLoader;
        $this->headerReader   = new UblDocumentHeaderReader();
        $this->contentReader  = new UblDocumentContentReader();
    }

    /**
     * Reads UBL XML into a document value.
     *
     * @param  string                 $xml UBL Invoice or CreditNote XML to read into domain values.
     * @return UblDocument
     * @throws UblValidationException when the XML is unsafe, malformed or unsupported
     * @throws InvalidInputException  when an identifier in the parsed document is invalid
     */
    public function read(string $xml): UblDocument
    {
        // Parse the raw XML securely, enforce its size limit, and accept only Invoice or CreditNote roots.
        $document = $this->documentLoader->parse($xml);

        // Extract the package's typed header and content values from the validated DOM document.
        return $this->readDocument($document);
    }

    /**
     * Reads an already parsed UBL document into domain values.
     *
     * @param  UblXmlDocument        $document Parsed UBL document to read into domain values.
     * @return UblDocument
     * @throws InvalidInputException when an identifier in the parsed document is invalid
     */
    public function readDocument(UblXmlDocument $document): UblDocument
    {
        // Query a detached DOM copy; register UBL namespaces once for all field-reader XPath expressions.
        $xpath = new UblXPath($document->domDocument());
        // The parsed root was already checked by the loader; its local name chooses Invoice or CreditNote paths.
        $kind = $document->kind();

        // Read the header first because line amounts without currencyID inherit the document currency.
        $header  = $this->headerReader->read($xpath, $kind);
        $content = $this->contentReader->read($xpath, $kind, $header->currency());

        // Keep the parsed XML separate from the public value object returned to callers.
        return new UblDocument($header, $content);
    }
}
