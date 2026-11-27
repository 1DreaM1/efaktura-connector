<?php

namespace DreaM1\EfakturaConnector\Domain\EFaktura;

/** Page-size bounds accepted for provider pagination requests. */
final class EFakturaProviderDataLimits
{
    /** Minimum page size accepted for provider list requests. */
    public const PAGE_SIZE_MIN = 1;

    /** Maximum page size accepted for provider document list requests. */
    public const PAGE_SIZE_MAX = 100;
}
