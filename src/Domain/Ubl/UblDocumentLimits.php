<?php

namespace DreaM1\EfakturaConnector\Domain\Ubl;

/** Shared size limits for UBL document content. */
final class UblDocumentLimits
{
    /** Maximum accepted UBL XML document size in kilobytes. */
    public const MAX_XML_SIZE_KILOBYTES = 10240;
    /** Maximum accepted UBL XML document size in bytes. */
    public const MAX_XML_SIZE_BYTES = self::MAX_XML_SIZE_KILOBYTES * 1024;
}
