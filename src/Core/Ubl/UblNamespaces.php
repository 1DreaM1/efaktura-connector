<?php

namespace DreaM1\EfakturaConnector\Core\Ubl;

use DOMElement;
use DreaM1\EfakturaConnector\Domain\Ubl\UblDocumentRoot;
use DreaM1\EfakturaConnector\Support\Exceptions\InvalidInputException;

/** XML namespace URIs used by the package's supported UBL elements. */
final class UblNamespaces
{
    /** XPath prefix bound to the UBL Common Aggregate Components namespace. */
    public const CAC_PREFIX = 'cac';
    /** Base URI used to construct UBL document namespaces. */
    public const DOCUMENT_BASE = 'urn:oasis:names:specification:ubl:schema:xsd:';
    /** Namespace URI for UBL Common Aggregate Components version 2. */
    public const COMMON_AGGREGATE_COMPONENTS = 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2';
    /** Namespace URI for UBL Common Basic Components version 2. */
    public const COMMON_BASIC_COMPONENTS = 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2';
    /** XPath prefix bound to the UBL Common Basic Components namespace. */
    public const CBC_PREFIX = 'cbc';
    /** Prefix used for XML namespace declaration attributes. */
    public const XMLNS_PREFIX = 'xmlns';
    /** Namespace URI for XML namespace declaration attributes. */
    public const XMLNS_URI = 'http://www.w3.org/2000/xmlns/';

    /**
     * Returns the UBL document namespace for a supported root kind.
     *
     * @param  string                $kind UBL root local name, either Invoice or CreditNote.
     * @throws InvalidInputException when the kind is not Invoice or CreditNote.
     */
    public static function documentNamespaceFor(string $kind): string
    {
        if (! UblDocumentRoot::isUblDocument($kind)) {
            throw new InvalidInputException('A UBL Invoice or CreditNote root is required.');
        }

        return self::DOCUMENT_BASE . $kind . '-' . UblVersion::MAJOR_VERSION;
    }

    /** Whether a root element is a supported UBL Invoice or CreditNote in the configured namespace. */
    public static function isSupportedRoot(?DOMElement $root): bool
    {
        $kind = $root === null ? null : $root->localName;

        if ($root === null || $kind === null || ! UblDocumentRoot::isUblDocument($kind)) {
            return false;
        }

        return $root->namespaceURI === self::documentNamespaceFor($kind);
    }
}
