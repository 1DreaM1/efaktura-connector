<?php

namespace DreaM1\EfakturaConnector\Domain\Ubl\Draft;

/** Shared normalization for optional text values in UBL drafts. */
final class UblDraftText
{
    /**
     * Converts only an empty string to null and preserves all other input verbatim.
     *
     * @param  string|null $value Optional text value to normalize.
     * @return string|null Null for an empty string; otherwise the original value.
     */
    public static function emptyToNull(?string $value): ?string
    {
        return $value === '' ? null : $value;
    }
}
