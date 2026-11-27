<?php

namespace DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Logging;

use DOMDocument;
use DOMElement;
use DOMException;
use DOMXPath;
use DreaM1\EfakturaConnector\Core\Ubl\UblAttributeName;
use DreaM1\EfakturaConnector\Core\Ubl\UblElementName;
use DreaM1\EfakturaConnector\Core\Ubl\UblNamespaces;
use DreaM1\EfakturaConnector\Domain\EFaktura\EFakturaDocumentLimits;
use DreaM1\EfakturaConnector\Support\NonBlankString;
use DreaM1\EfakturaConnector\Support\Xml\SecureXmlDocumentParser;
use DreaM1\EfakturaConnector\Support\Xml\XmlParseException;

/**
 * Redacts sensitive UBL values while preserving the document structure.
 *
 * The default is to redact every non-whitespace text node and every non-namespace attribute.
 * Only bounded numeric values and known technical codes are allowlisted. Extending these lists
 * requires confirming that the added field cannot contain names, identifiers, free text or secrets.
 */
final class UblPayloadRedactor
{
    /** Pattern for uppercase three-letter currency codes retained in diagnostic XML. */
    private const SAFE_CURRENCY_CODE_PATTERN = '/\A[A-Z]{3}\z/';
    /** Pattern for technical document type codes retained in diagnostic XML. */
    private const SAFE_DOCUMENT_TYPE_CODE_PATTERN = '/\A[A-Za-z0-9][A-Za-z0-9._:-]*\z/';
    /** Pattern for plain decimal values without exponent notation. */
    private const SAFE_DECIMAL_PATTERN = '/\A[+-]?(?:\d+(?:\.\d*)?|\.\d+)\z/';
    /** Pattern for one- to three-character uppercase unit codes. */
    private const SAFE_UNIT_CODE_PATTERN = '/\A[A-Z0-9]{1,3}\z/';
    /** Pattern for technical codes allowed in selected XML attributes. */
    private const SAFE_ATTRIBUTE_CODE_PATTERN = '/\A[A-Za-z0-9][A-Za-z0-9._:\/-]*\z/';
    /** Maximum length of an identifier allowed in diagnostic XML. */
    private const MAX_SAFE_IDENTIFIER_LENGTH = 256;
    /** Maximum length of a code allowed in diagnostic XML. */
    private const MAX_SAFE_CODE_LENGTH = 64;
    /** Maximum length of an attribute value allowed in diagnostic XML. */
    private const MAX_SAFE_ATTRIBUTE_VALUE_LENGTH = 128;

    /** @var string[] UBL text element names whose bounded technical values may be retained. */
    private const SAFE_TEXT_ELEMENTS = [
        UblElementName::CREDIT_NOTE_TYPE_CODE,
        UblElementName::CREDITED_QUANTITY,
        UblElementName::CUSTOMIZATION_ID,
        UblElementName::DOCUMENT_CURRENCY_CODE,
        UblElementName::INVOICED_QUANTITY,
        UblElementName::INVOICE_TYPE_CODE,
        UblElementName::LINE_EXTENSION_AMOUNT,
        UblElementName::PAYABLE_AMOUNT,
        UblElementName::PAYABLE_ROUNDING_AMOUNT,
        UblElementName::PERCENT,
        UblElementName::PRICE_AMOUNT,
        UblElementName::PROFILE_ID,
        UblElementName::TAX_AMOUNT,
        UblElementName::TAX_EXCLUSIVE_AMOUNT,
        UblElementName::TAX_INCLUSIVE_AMOUNT,
        UblElementName::TAXABLE_AMOUNT,
    ];

    /** @var string[] UBL attribute names whose bounded technical values may be retained. */
    private const SAFE_ATTRIBUTES = [
        UblAttributeName::CURRENCY_ID,
        UblAttributeName::LIST_ID,
        UblAttributeName::SCHEME_ID,
        UblAttributeName::UNIT_CODE,
    ];

    /**
     * Returns UBL XML with unsafe values redacted, or a placeholder for unsafe input.
     *
     * @param  string       $payload UBL XML payload to redact before it is written to a log.
     * @return string
     * @throws DOMException when a DOM operation fails while redacting the UBL payload.
     */
    public static function redact(string $payload): string
    {
        if (strlen($payload) > EFakturaDocumentLimits::MAX_XML_SIZE_BYTES) {
            // Do not parse oversized input just to produce a log representation.
            return SensitiveDataRedactor::REDACTED_VALUE;
        }

        $document = self::parseForRedaction($payload);

        if ($document === null) {
            return SensitiveDataRedactor::REDACTED_VALUE;
        }

        $xpath = new DOMXPath($document);

        self::redactTextNodes($xpath);
        self::redactAttributes($document);
        self::removeUnsafeNodes($xpath);

        return $document->saveXML($document->documentElement) ?: SensitiveDataRedactor::REDACTED_VALUE;
    }

    /** Parses XML for redaction and hides any input whose structure cannot be trusted. */
    private static function parseForRedaction(string $payload): ?DOMDocument
    {
        try {
            return SecureXmlDocumentParser::parse($payload);
        } catch (XmlParseException $exception) {
            // Malformed or unsafe XML has no trustworthy structure to sanitize, so redact it completely.
            return null;
        }
    }

    /** Redacts all text except validated values in known technical CBC fields. */
    private static function redactTextNodes(DOMXPath $xpath): void
    {
        $textNodes = $xpath->query('//text()');

        if (! $textNodes) {
            return;
        }

        foreach ($textNodes as $textNode) {
            if (! NonBlankString::isNotBlank((string) $textNode->nodeValue)) {
                continue;
            }

            // Retain only validated values from known CBC fields; party data and free text stay redacted.
            $parent = $textNode->parentNode;

            if (
                ! $parent instanceof DOMElement
                || $parent->namespaceURI !== UblNamespaces::COMMON_BASIC_COMPONENTS
                || ! in_array($parent->localName, self::SAFE_TEXT_ELEMENTS, true)
                || ! self::isSafeTextValue($parent->localName, $textNode->nodeValue)
            ) {
                $textNode->nodeValue = SensitiveDataRedactor::REDACTED_VALUE;
            }
        }
    }

    /** Redacts attributes except namespace declarations and validated technical CBC codes. */
    private static function redactAttributes(DOMDocument $document): void
    {
        $elements = $document->getElementsByTagName('*');

        foreach ($elements as $element) {
            foreach ($element->attributes as $attribute) {
                // Namespace declarations preserve the XML structure; every data attribute needs an allowlisted code.
                if (
                    $attribute->namespaceURI === UblNamespaces::XMLNS_URI
                    || ($element->namespaceURI === UblNamespaces::COMMON_BASIC_COMPONENTS
                        && $attribute->namespaceURI === null
                        && in_array($attribute->localName, self::SAFE_ATTRIBUTES, true)
                        && self::isSafeAttributeValue($attribute->localName, (string) $attribute->nodeValue))
                ) {
                    continue;
                }

                $attribute->nodeValue = SensitiveDataRedactor::REDACTED_VALUE;
            }
        }
    }

    /** Removes comments and processing instructions that could carry unstructured sensitive text. */
    private static function removeUnsafeNodes(DOMXPath $xpath): void
    {
        $unsafeNodes = $xpath->query('//comment() | //processing-instruction()');

        if ($unsafeNodes) {
            foreach ($unsafeNodes as $unsafeNode) {
                // Comments and processing instructions can carry sensitive text outside normal UBL values.
                $unsafeNode->parentNode->removeChild($unsafeNode);
            }
        }
    }

    /** Allows only known non-sensitive UBL element values into diagnostic output. */
    private static function isSafeTextValue(string $elementName, string $value): bool
    {
        $value = trim($value);

        if ($value === '') {
            return false;
        }

        if ($elementName === UblElementName::DOCUMENT_CURRENCY_CODE) {
            return preg_match(self::SAFE_CURRENCY_CODE_PATTERN, $value) === 1;
        }

        if (in_array($elementName, [UblElementName::CUSTOMIZATION_ID, UblElementName::PROFILE_ID], true)) {
            return strlen($value) <= self::MAX_SAFE_IDENTIFIER_LENGTH
                && preg_match(ApiLogValuePolicy::SAFE_TECHNICAL_IDENTIFIER_PATTERN, $value) === 1;
        }

        if (in_array($elementName, [UblElementName::CREDIT_NOTE_TYPE_CODE, UblElementName::INVOICE_TYPE_CODE], true)) {
            return strlen($value) <= self::MAX_SAFE_CODE_LENGTH
                && preg_match(self::SAFE_DOCUMENT_TYPE_CODE_PATTERN, $value) === 1;
        }

        return preg_match(self::SAFE_DECIMAL_PATTERN, $value) === 1;
    }

    /** Allows only bounded code-like UBL attributes in diagnostic output. */
    private static function isSafeAttributeValue(string $attributeName, string $value): bool
    {
        if ($attributeName === UblAttributeName::CURRENCY_ID) {
            return preg_match(self::SAFE_CURRENCY_CODE_PATTERN, $value) === 1;
        }

        if ($attributeName === UblAttributeName::UNIT_CODE) {
            return preg_match(self::SAFE_UNIT_CODE_PATTERN, $value) === 1;
        }

        return strlen($value) <= self::MAX_SAFE_ATTRIBUTE_VALUE_LENGTH
            && preg_match(self::SAFE_ATTRIBUTE_CODE_PATTERN, $value) === 1;
    }
}
