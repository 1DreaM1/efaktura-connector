<?php

namespace DreaM1\EfakturaConnector\Infrastructure\EFaktura\Http\Protocol;

/** Media type values used in HTTP Accept and Content-Type headers. */
final class MediaType
{
    /** HTTP media type 'application/json' supported by the connector transport. */
    public const APPLICATION_JSON = 'application/json';
    /** HTTP media type for XML supported by the connector transport. */
    public const APPLICATION_XML = 'application/xml';
}
