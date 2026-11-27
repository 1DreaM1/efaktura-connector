<?php

namespace DreaM1\EfakturaConnector\Infrastructure\Ubl\Reader;

use DreaM1\EfakturaConnector\Infrastructure\Ubl\UblXPathExpressionBuilder;

/**
 * @internal Short names for the XPath builder calls the UBL readers make on nearly every line.
 *
 * Each method delegates to UblXPathExpressionBuilder, which stays the single place that knows the cbc/cac aliases.
 */
trait BuildsUblXPaths
{
    /** XPath name of a common basic component, for example cbc:ID. */
    private static function cbc(string $elementName): string
    {
        return UblXPathExpressionBuilder::cbc($elementName);
    }

    /** XPath name of a common aggregate component, for example cac:Party. */
    private static function cac(string $elementName): string
    {
        return UblXPathExpressionBuilder::cac($elementName);
    }

    /** Joins XPath segments with `/`. */
    private static function path(string ...$segments): string
    {
        return UblXPathExpressionBuilder::path(...$segments);
    }

    /** XPath of a cbc element directly under the given parent path. */
    private static function cbcChild(string $parent, string $elementName): string
    {
        return UblXPathExpressionBuilder::cbcChild($parent, $elementName);
    }

    /** XPath of a cac element directly under the given parent path. */
    private static function cacChild(string $parent, string $elementName): string
    {
        return UblXPathExpressionBuilder::cacChild($parent, $elementName);
    }
}
