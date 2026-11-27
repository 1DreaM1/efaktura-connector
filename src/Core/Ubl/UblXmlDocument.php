<?php

namespace DreaM1\EfakturaConnector\Core\Ubl;

use DOMDocument;
use DreaM1\EfakturaConnector\Core\Ubl\Exceptions\InvalidXmlException;
use DreaM1\EfakturaConnector\Core\Ubl\Exceptions\UnsupportedDocumentTypeException;
use DreaM1\EfakturaConnector\Domain\Ubl\UblDocumentLimits;
use DreaM1\EfakturaConnector\Support\Xml\SecureXmlDocumentParser;
use DreaM1\EfakturaConnector\Support\Xml\XmlParseException;

/** Parsed, supported UBL XML document shared by readers and validators. */
final class UblXmlDocument
{
    private string $xml;
    private DOMDocument $document;
    private string $kind;

    /** Stores the XML and DOM after the factory has verified their size, safety and supported root. */
    private function __construct(string $xml, DOMDocument $document, string $kind)
    {
        $this->xml      = $xml;
        $this->document = $document;
        $this->kind     = $kind;
    }

    /**
     * Securely parses XML and accepts only a supported UBL Invoice or CreditNote.
     *
     * @param  string                           $xml XML text for the supported UBL document.
     * @return self
     * @throws InvalidXmlException              when the XML is malformed, too large, or contains a DTD.
     * @throws UnsupportedDocumentTypeException when the root is not a supported UBL Invoice or CreditNote.
     */
    public static function fromXml(string $xml): self
    {
        // Reject oversized input before building a DOM tree.
        if (strlen($xml) > UblDocumentLimits::MAX_XML_SIZE_BYTES) {
            throw InvalidXmlException::unsafe();
        }

        try {
            // The shared parser applies the package's XML safety rules before returning the DOM.
            $document = SecureXmlDocumentParser::parse($xml);
        } catch (XmlParseException $exception) {
            // The secure parser throws this exception only when its DTD guard rejects the input.
            throw InvalidXmlException::unsafe();
        }

        if ($document === null) {
            // A null result means the XML was not well formed enough to produce a DOM document.
            throw InvalidXmlException::malformed();
        }

        // Accept only the Invoice/CreditNote root name paired with its expected UBL version namespace.
        $root = $document->documentElement;

        if ($root === null || ! UblNamespaces::isSupportedRoot($root) || $root->localName === null) {
            throw new UnsupportedDocumentTypeException();
        }

        return new self($xml, $document, $root->localName);
    }

    /** Returns the original XML text. */
    public function xml(): string
    {
        return $this->xml;
    }

    /** Returns the supported root element name. */
    public function kind(): string
    {
        return $this->kind;
    }

    /** Returns a detached copy of the parsed document. */
    public function domDocument(): DOMDocument
    {
        return clone $this->document;
    }
}
