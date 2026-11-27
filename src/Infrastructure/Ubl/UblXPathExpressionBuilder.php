<?php

namespace DreaM1\EfakturaConnector\Infrastructure\Ubl;

use DreaM1\EfakturaConnector\Core\Ubl\UblNamespaces;

/** Builds XPath names using the registered cbc/basic and cac/aggregate UBL namespace aliases. */
final class UblXPathExpressionBuilder
{
    private function __construct()
    {
    }

    /**
     * Qualifies a common basic component with the cbc namespace alias, for example cbc:ID.
     *
     * @param  string $elementName Local name of the basic component.
     * @return string Qualified XPath name using the CBC namespace prefix.
     */
    public static function cbc(string $elementName): string
    {
        return UblNamespaces::CBC_PREFIX . ':' . $elementName;
    }

    /**
     * Qualifies a common aggregate component with the cac namespace alias, for example cac:Party.
     *
     * @param  string $elementName Local name of the aggregate component.
     * @return string Qualified XPath name using the CAC namespace prefix.
     */
    public static function cac(string $elementName): string
    {
        return UblNamespaces::CAC_PREFIX . ':' . $elementName;
    }

    /**
     * Joins ordered XPath path segments with `/`; callers provide any predicates or attribute selectors.
     *
     * @param  string ...$segments Ordered XPath names or expressions.
     * @return string Combined XPath expression.
     */
    public static function path(string ...$segments): string
    {
        return implode('/', $segments);
    }

    /**
     * Builds the path of a common basic component directly under a parent path.
     *
     * @param  string $parent      XPath of the parent element.
     * @param  string $elementName Local name of the basic component.
     * @return string XPath such as cac:Party/cbc:ID.
     */
    public static function cbcChild(string $parent, string $elementName): string
    {
        return self::path($parent, self::cbc($elementName));
    }

    /**
     * Builds the path of a common aggregate component directly under a parent path.
     *
     * @param  string $parent      XPath of the parent element.
     * @param  string $elementName Local name of the aggregate component.
     * @return string XPath such as cac:Party/cac:PostalAddress.
     */
    public static function cacChild(string $parent, string $elementName): string
    {
        return self::path($parent, self::cac($elementName));
    }
}
