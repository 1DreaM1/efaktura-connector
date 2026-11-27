<?php

namespace DreaM1\EfakturaConnector\Core\Ubl\Validation;

/** Parsed result of running one Schematron stylesheet against a document. */
final class SchematronReport
{
    private string $xml;

    /**
     * @param string $xml Schematron Validation Report Language (SVRL) XML produced by the runner.
     */
    public function __construct(string $xml)
    {
        $this->xml = $xml;
    }

    /**
     * Returns the XML.
     *
     * @return string
     */
    public function xml(): string
    {
        return $this->xml;
    }
}
