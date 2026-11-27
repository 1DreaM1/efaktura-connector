<?php

namespace DreaM1\EfakturaConnector\Support\Xml;

use DOMDocument;
use DreaM1\EfakturaConnector\Support\NonBlankString;

/** Securely parses XML without network access and rejects DTD declarations before parsing. @internal */
final class SecureXmlDocumentParser
{
    /**
     * Parses XML without network access; returns null for malformed or blank input and rejects DTDs.
     *
     * @param  string            $xml Raw XML text to parse without network access.
     * @return DOMDocument|null
     * @throws XmlParseException when the XML contains a document type declaration.
     */
    public static function parse(string $xml): ?DOMDocument
    {
        if (! NonBlankString::isNotBlank($xml)) {
            return null;
        }

        if (XmlDtdDeclarationScanner::containsDocumentTypeDeclaration($xml)) {
            throw XmlParseException::unsafe();
        }

        return LibxmlErrorScope::run(static function () use ($xml): ?DOMDocument {
            $document = new DOMDocument();

            if (! $document->loadXML($xml, LIBXML_NONET) || $document->documentElement === null) {
                return null;
            }

            // Keep a post-parse check in case an unusual XML encoding bypassed the lexical scan.
            if ($document->doctype !== null) {
                throw XmlParseException::unsafe();
            }

            return $document;
        });
    }
}
