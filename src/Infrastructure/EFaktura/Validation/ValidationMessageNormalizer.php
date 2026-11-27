<?php

namespace DreaM1\EfakturaConnector\Infrastructure\EFaktura\Validation;

/** Trims a validation message and folds repeated whitespace into one space. */
final class ValidationMessageNormalizer
{
    private function __construct()
    {
    }
    /**
     * Returns a compact, single-line validation message.
     *
     * @param  string $message Original validation message.
     * @return string Message with repeated whitespace folded and outer whitespace removed.
     */
    public static function normalize(string $message): string
    {
        return trim(preg_replace('/\s+/', ' ', $message) ?? '');
    }
}
