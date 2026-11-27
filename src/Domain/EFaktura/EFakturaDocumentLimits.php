<?php

namespace DreaM1\EfakturaConnector\Domain\EFaktura;

use DreaM1\EfakturaConnector\Domain\Ubl\UblDocumentLimits;

/** Size limits applied to outbound eFaktura document payloads. */
final class EFakturaDocumentLimits
{
    /** Maximum accepted UBL XML document size in kilobytes. */
    public const MAX_XML_SIZE_KILOBYTES = UblDocumentLimits::MAX_XML_SIZE_KILOBYTES;
    /** Maximum accepted UBL XML document size in bytes. */
    public const MAX_XML_SIZE_BYTES = UblDocumentLimits::MAX_XML_SIZE_BYTES;
    private function __construct()
    {
    }
}
