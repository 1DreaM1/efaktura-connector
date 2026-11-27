<?php

namespace DreaM1\EfakturaConnector\Core\ProviderErrors;

use DreaM1\EfakturaConnector\Support\StringValue;

/** Typed category reported in a provider error envelope. */
final class ProviderErrorCategory
{
    use StringValue;

    /** Provider error category 'AUTH'. */
    public const AUTH = 'AUTH';
    /** Provider error category 'VALIDATION'. */
    public const VALIDATION = 'VALIDATION';
    /** Provider error category 'PROCESSING'. */
    public const PROCESSING = 'PROCESSING';
    /** Provider error category 'TEMPORARY'. */
    public const TEMPORARY = 'TEMPORARY';
    /** Provider error category 'PERMANENT'. */
    public const PERMANENT = 'PERMANENT';

    /** Categories currently documented by the provider API. */
    public const KNOWN_CATEGORIES = [
        self::AUTH,
        self::VALIDATION,
        self::PROCESSING,
        self::TEMPORARY,
        self::PERMANENT,
    ];

    /** Whether this category is part of the vocabulary documented by the provider API. */
    public function isKnown(): bool
    {
        return in_array($this->value, self::KNOWN_CATEGORIES, true);
    }

    protected static function normalize(string $value): string
    {
        return $value;
    }
}
