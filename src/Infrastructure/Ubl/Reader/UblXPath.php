<?php

namespace DreaM1\EfakturaConnector\Infrastructure\Ubl\Reader;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;
use DreaM1\EfakturaConnector\Core\Ubl\UblAttributeName;
use DreaM1\EfakturaConnector\Core\Ubl\UblNamespaces;
use DreaM1\EfakturaConnector\Domain\Ubl\UblEndpointIdentifier;

/**
 * XPath queries over a UBL document with cbc/cac aliases bound to the UBL component namespace URIs.
 * Scalar values are trimmed; EndpointID text and schemeID are preserved as represented in UBL.
 *
 * @internal
 */
final class UblXPath
{
    private DOMXPath $xpath;

    /**
     * @param DOMDocument $document Parsed UBL document to query.
     */
    public function __construct(DOMDocument $document)
    {
        $this->xpath = new DOMXPath($document);
        // XPath prefixes are local aliases and do not depend on prefixes chosen in the source XML.
        $this->xpath->registerNamespace(UblNamespaces::CBC_PREFIX, UblNamespaces::COMMON_BASIC_COMPONENTS);
        $this->xpath->registerNamespace(UblNamespaces::CAC_PREFIX, UblNamespaces::COMMON_AGGREGATE_COMPONENTS);
    }

    /**
     * First scalar text value at a root-relative path; missing or whitespace-only text becomes null.
     *
     * @param  string      $path Relative XPath expression evaluated from the document root.
     * @return string|null
     */
    public function value(string $path): ?string
    {
        return $this->evaluate('/*/' . $path);
    }

    /**
     * First non-empty scalar value from root-relative paths, checked in the order supplied.
     *
     * @param  string      ...$paths Relative XPath expressions checked in order.
     * @return string|null
     */
    public function firstValue(string ...$paths): ?string
    {
        return $this->firstValueFromPaths($paths);
    }

    /**
     * First scalar text value at a path relative to a context node; missing or whitespace-only text becomes null.
     *
     * @param  DOMNode     $node XML node used as the XPath context.
     * @param  string      $path Relative XPath expression evaluated from the context node.
     * @return string|null
     */
    public function nodeValue(DOMNode $node, string $path): ?string
    {
        return $this->evaluate($path, $node);
    }

    /**
     * First non-empty scalar value from paths relative to a context node, checked in the order supplied.
     *
     * @param  DOMNode     $node     XML node used as the XPath context.
     * @param  string      ...$paths Relative XPath expressions checked in order.
     * @return string|null
     */
    public function firstNodeValue(DOMNode $node, string ...$paths): ?string
    {
        return $this->firstValueFromPaths($paths, $node);
    }

    /**
     * Returns all non-empty, trimmed text values selected from the document root.
     *
     * @param  string   $path Relative XPath expression selecting values from the document root.
     * @return string[] non-empty trimmed values of every node of a path relative to the root element
     */
    public function values(string $path): array
    {
        $values = [];

        foreach ($this->nodes($path) as $node) {
            $value = trim($node->textContent);

            if ($value !== '') {
                $values[] = $value;
            }
        }

        return $values;
    }

    /**
     * Returns the DOM nodes selected by the XPath expression.
     *
     * @param  string    $path Relative XPath expression selecting nodes from the document root.
     * @return DOMNode[] nodes of a path relative to the root element
     */
    public function nodes(string $path): array
    {
        return $this->queryNodes('/*/' . $path);
    }

    /**
     * Returns nodes selected by an XPath expression, optionally relative to a context node.
     * A failed query or a query with no matches returns an empty array.
     *
     * @param  string       $expression XPath expression used to query the XML document.
     * @param  DOMNode|null $context    Node used as the XPath context.
     * @return DOMNode[]
     */
    public function queryNodes(string $expression, ?DOMNode $context = null): array
    {
        $nodes = $this->xpath->query($expression, $context);

        // A failed XPath query has no matches from the reader's point of view.
        if ($nodes === false) {
            return [];
        }

        $matches = [];

        foreach ($nodes as $node) {
            // Namespace nodes are not DOM nodes; the readers only consume elements, attributes and text.
            if ($node instanceof DOMNode) {
                $matches[] = $node;
            }
        }

        return $matches;
    }

    /**
     * The cbc:EndpointID selected by a root-relative path as a typed value and schemeID pair; null when absent or blank.
     *
     * @param  string                     $endpointPath Relative XPath expression selecting cbc:EndpointID.
     * @return UblEndpointIdentifier|null
     */
    public function endpoint(string $endpointPath): ?UblEndpointIdentifier
    {
        $endpoint = $this->nodes($endpointPath)[0] ?? null;

        if (! $endpoint instanceof DOMElement) {
            return null;
        }

        $scheme = $endpoint->hasAttribute(UblAttributeName::SCHEME_ID)
            ? $endpoint->getAttribute(UblAttributeName::SCHEME_ID)
            : null;

        $value = $endpoint->textContent;

        if (trim($value) === '') {
            return null;
        }

        return UblEndpointIdentifier::fromSchemeAndValue($scheme, $value);
    }

    /** @param string[] $paths Relative XPath expressions checked in order. */
    private function firstValueFromPaths(array $paths, ?DOMNode $node = null): ?string
    {
        // Paths are checked in caller-supplied priority order; the first non-empty value wins.
        foreach ($paths as $path) {
            $value = $node === null ? $this->value($path) : $this->nodeValue($node, $path);

            if ($value !== null) {
                return $value;
            }
        }

        return null;
    }

    /** Evaluates a string expression and maps empty node text to null. */
    private function evaluate(string $path, ?DOMNode $context = null): ?string
    {
        // string() yields a scalar for present and missing nodes; normalize whitespace-only values to null.
        $value = trim((string) $this->xpath->evaluate('string(' . $path . ')', $context));

        return $value === '' ? null : $value;
    }
}
