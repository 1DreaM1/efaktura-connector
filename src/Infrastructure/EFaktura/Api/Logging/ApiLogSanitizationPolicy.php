<?php

namespace DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Logging;

/** Shared limits and markers for values retained in API communication logs. @internal */
final class ApiLogSanitizationPolicy
{
    /** Maximum number of array entries retained in a sanitized log value. */
    public const MAX_ARRAY_ITEMS = 100;
    /** Maximum nested array depth retained in a sanitized log value. */
    public const MAX_NESTING_DEPTH = 16;
    /** Associative-array key used to make item-limit truncation visible in logs. */
    public const TRUNCATION_MARKER_KEY = '_efaktura_log_truncated';
    /** Marker used when a sanitized log value is truncated. */
    public const TRUNCATED_VALUE = '[truncated]';

    /** Prevents instantiation of the shared policy constants holder. */
    private function __construct()
    {
    }
}
