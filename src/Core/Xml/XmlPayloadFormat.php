<?php

namespace DreaM1\EfakturaConnector\Core\Xml;

/** XML payload format labels used by the connector APIs. */
final class XmlPayloadFormat
{
    /** Generic XML payload format label. */
    public const XML = 'XML';
    /** UBL XML payload format label. */
    public const UBL_XML = 'UBL_XML';
    /** XML payload with a Standard Business Document Header. */
    public const SBDH_XML = 'SBDH_XML';

    /** Prevents instantiation of this constants class. */
    private function __construct()
    {
    }
}
