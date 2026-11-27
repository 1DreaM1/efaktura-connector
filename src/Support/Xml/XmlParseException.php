<?php

namespace DreaM1\EfakturaConnector\Support\Xml;

use DreaM1\EfakturaConnector\Support\Exceptions\ConnectorThrowable;
use RuntimeException;

/** @internal XML parsing failed because the input contains a prohibited document type declaration. */
final class XmlParseException extends RuntimeException implements ConnectorThrowable
{
    /** Restricts construction to parser safety failures. */
    private function __construct()
    {
        parent::__construct('XML contains a prohibited DTD declaration.');
    }

    /** Creates an exception for XML containing a prohibited document type declaration. */
    public static function unsafe(): self
    {
        return new self();
    }
}
