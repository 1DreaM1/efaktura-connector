<?php

namespace DreaM1\EfakturaConnector\Infrastructure\Ubl\Writer;

use DOMElement;
use DOMException;
use DreaM1\EfakturaConnector\Core\Ubl\UblAttributeName;
use DreaM1\EfakturaConnector\Core\Ubl\UblNamespaces;
use DreaM1\EfakturaConnector\Domain\Ubl\Draft\UblDecimal;
use DreaM1\EfakturaConnector\Domain\Ubl\Draft\UblDraftIdentifier;
use DreaM1\EfakturaConnector\Domain\Ubl\LocalDate;
use DreaM1\EfakturaConnector\Support\Exceptions\InvalidInputException;
use DreaM1\EfakturaConnector\Support\Exceptions\InvariantViolationException;
use DreaM1\EfakturaConnector\Support\Xml\XmlCharacters;

/** Creates cbc:* basic and cac:* aggregate elements in their UBL namespaces. */
final class UblElementWriter
{
    /**
     * Appends a UBL common basic component element.
     * @param  string       $name   local element name without a namespace prefix
     * @param  string       $value  text content; an empty value leaves the element empty
     * @param  DOMElement   $parent Parent XML element receiving the new child element.
     * @return DOMElement
     * @throws DOMException when the DOM rejects an XML element or attribute operation.
     */
    public static function appendCbc(DOMElement $parent, string $name, string $value = ''): DOMElement
    {
        return self::createElement(
            $parent,
            UblNamespaces::COMMON_BASIC_COMPONENTS,
            UblNamespaces::CBC_PREFIX,
            $name,
            $value
        );
    }

    /**
     * Appends a UBL decimal to a common basic component.
     *
     * @param  DOMElement   $parent Parent XML element receiving the new child.
     * @param  string       $name   Local element name without a namespace prefix.
     * @param  UblDecimal   $value  Decimal value serialized as text.
     * @return DOMElement
     * @throws DOMException when the DOM rejects an XML element or attribute operation.
     */
    public static function appendDecimalCbc(DOMElement $parent, string $name, UblDecimal $value): DOMElement
    {
        return self::appendCbc($parent, $name, $value->value());
    }

    /**
     * Appends a UBL common aggregate component element.
     * @param  string       $name   local element name without a namespace prefix
     * @param  string       $value  text content; an empty value leaves the element empty
     * @param  DOMElement   $parent Parent XML element receiving the new child element.
     * @return DOMElement
     * @throws DOMException when the DOM rejects an XML element or attribute operation.
     */
    public static function appendCac(DOMElement $parent, string $name, string $value = ''): DOMElement
    {
        return self::createElement(
            $parent,
            UblNamespaces::COMMON_AGGREGATE_COMPONENTS,
            UblNamespaces::CAC_PREFIX,
            $name,
            $value
        );
    }

    /**
     * Appends an amount with its UBL currency attribute.
     *
     * @param  DOMElement   $parent   Parent XML element receiving the amount.
     * @param  string       $name     Local element name for the amount.
     * @param  string       $amount   Decimal amount text.
     * @param  string       $currency Currency code written to the currencyID attribute.
     * @throws DOMException when the DOM rejects an XML element or attribute operation.
     */
    public static function appendAmount(
        DOMElement $parent,
        string $name,
        string $amount,
        string $currency
    ): void {
        self::setAttribute(self::appendCbc($parent, $name, $amount), UblAttributeName::CURRENCY_ID, $currency);
    }

    /**
     * Appends a decimal amount with its UBL currency attribute.
     *
     * @param  DOMElement   $parent   Parent XML element receiving the amount.
     * @param  string       $name     Local element name for the amount.
     * @param  UblDecimal   $amount   Decimal amount value.
     * @param  string       $currency Currency code written to the currencyID attribute.
     * @throws DOMException when the DOM rejects an XML element or attribute operation.
     */
    public static function appendDecimalAmount(
        DOMElement $parent,
        string $name,
        UblDecimal $amount,
        string $currency
    ): void {
        self::appendAmount($parent, $name, $amount->value(), $currency);
    }

    /**
     * Appends an optional decimal amount when present.
     *
     * @param  DOMElement      $parent   Parent XML element receiving the amount.
     * @param  string          $name     Local element name for the amount.
     * @param  UblDecimal|null $amount   Decimal amount, or null to omit it.
     * @param  string          $currency Currency code written to the currencyID attribute.
     * @throws DOMException    when the DOM rejects an XML element or attribute operation.
     */
    public static function appendOptionalDecimalAmount(
        DOMElement $parent,
        string $name,
        ?UblDecimal $amount,
        string $currency
    ): void {
        if ($amount !== null) {
            self::appendDecimalAmount($parent, $name, $amount, $currency);
        }
    }

    /**
     * Appends a date when present.
     *
     * @param  DOMElement     $parent Parent XML element receiving the date.
     * @param  string         $name   Local element name for the date.
     * @param  LocalDate|null $date   Calendar date, or null to omit it.
     * @throws DOMException   when the DOM rejects an XML element or attribute operation.
     */
    public static function appendOptionalDate(DOMElement $parent, string $name, ?LocalDate $date): void
    {
        if ($date !== null) {
            self::appendCbc($parent, $name, $date->value());
        }
    }

    /**
     * Appends an identifier and its optional scheme.
     *
     * @param  DOMElement   $parent Parent XML element receiving the identifier.
     * @param  string       $name   Local element name for the identifier.
     * @param  string       $value  Identifier value.
     * @param  string|null  $scheme Scheme identifier, or null to omit the scheme attribute.
     * @throws DOMException when the DOM rejects an XML element or attribute operation.
     */
    public static function appendIdentifier(DOMElement $parent, string $name, string $value, ?string $scheme): void
    {
        $element = self::appendCbc($parent, $name, $value);
        self::setAttributeIfPresent($element, UblAttributeName::SCHEME_ID, $scheme);
    }

    /**
     * Appends a draft identifier together with its optional schemeID attribute.
     *
     * @param  DOMElement         $parent     Parent XML element receiving the identifier.
     * @param  string             $name       Local element name for the identifier.
     * @param  UblDraftIdentifier $identifier Identifier value and scheme to serialize.
     * @throws DOMException       when the DOM rejects an XML element or attribute operation.
     */
    public static function appendDraftIdentifier(DOMElement $parent, string $name, UblDraftIdentifier $identifier): void
    {
        self::appendIdentifier($parent, $name, $identifier->value(), $identifier->scheme());
    }

    /**
     * Appends a basic component only when its value is non-empty.
     *
     * @param  DOMElement   $parent Parent XML element receiving the component.
     * @param  string       $name   Local element name without a namespace prefix.
     * @param  string|null  $value  Text value, or null/empty to omit the component.
     * @throws DOMException when the DOM rejects an XML element or attribute operation.
     */
    public static function appendOptionalCbc(DOMElement $parent, string $name, ?string $value): void
    {
        if ($value !== null && $value !== '') {
            self::appendCbc($parent, $name, $value);
        }
    }

    /**
     * Appends an optional UBL decimal to a common basic component.
     *
     * @param  DOMElement      $parent Parent XML element receiving the component.
     * @param  string          $name   Local element name without a namespace prefix.
     * @param  UblDecimal|null $value  Decimal value, or null to omit the component.
     * @throws DOMException    when the DOM rejects an XML element or attribute operation.
     */
    public static function appendOptionalDecimalCbc(
        DOMElement $parent,
        string $name,
        ?UblDecimal $value
    ): void {
        if ($value !== null) {
            self::appendDecimalCbc($parent, $name, $value);
        }
    }

    /**
     * Sets an attribute when its value is non-empty.
     *
     * @param DOMElement  $element Element to update.
     * @param string      $name    Attribute name.
     * @param string|null $value   Attribute value, or null/empty to leave it unset.
     */
    public static function setAttributeIfPresent(DOMElement $element, string $name, ?string $value): void
    {
        if ($value !== null && $value !== '') {
            self::setAttribute($element, $name, $value);
        }
    }

    /**
     * Sets an attribute after checking that its value can be represented in XML.
     *
     * @param  DOMElement            $element Element to update.
     * @param  string                $name    Attribute name.
     * @param  string                $value   Attribute value, possibly empty.
     * @throws InvalidInputException when the value contains characters XML 1.0 does not allow.
     */
    public static function setAttribute(DOMElement $element, string $name, string $value): void
    {
        XmlCharacters::assertValid($value, 'UBL attribute ' . $name);
        $element->setAttribute($name, $value);
    }

    /** @throws DOMException */
    private static function createElement(
        DOMElement $parent,
        string $namespace,
        string $prefix,
        string $name,
        string $value
    ): DOMElement {
        if ($name === '' || strpos($name, ':') !== false) {
            throw new InvalidInputException('A UBL element name must be a non-empty local name.');
        }

        $document = $parent->ownerDocument;

        if ($document === null) {
            throw new InvariantViolationException('A UBL element can only be appended to a node that belongs to a document.');
        }

        // Reject text XML cannot carry; DOM would otherwise truncate at NUL or emit a malformed document.
        XmlCharacters::assertValid($value, 'UBL element ' . $name);

        // Build a namespaced DOM node rather than concatenating XML strings.
        $element = $document->createElementNS($namespace, $prefix . ':' . $name);

        if ($value !== '') {
            // Text nodes let DOM handle XML escaping for values supplied by the draft.
            $element->appendChild($document->createTextNode($value));
        }

        $parent->appendChild($element);

        return $element;
    }
}
