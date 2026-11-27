<?php

namespace DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Logging;

/** Shared character formats for safe scalar values retained in API logs. @internal */
final class ApiLogValuePolicy
{
    /** Pattern for short identifiers containing letters, digits and a limited punctuation set. */
    public const SAFE_IDENTIFIER_PATTERN = '/\A[\p{L}\p{N}._:~-]+\z/u';
    /** Pattern for provider codes containing ASCII letters, digits, dots, underscores or hyphens. */
    public const SAFE_PROVIDER_CODE_PATTERN = '/\A[A-Za-z0-9._-]+\z/';
    /** Pattern for technical identifiers that may also contain slashes and hash signs. */
    public const SAFE_TECHNICAL_IDENTIFIER_PATTERN = '/\A[\p{L}\p{N}._:#\/-]+\z/u';
    /** Pattern for structured field names used in safe diagnostics. */
    public const SAFE_FIELD_NAME_PATTERN = '/\A[A-Za-z_][A-Za-z0-9_.-]*\z/';
    /** Pattern for a Peppol participant identifier with a four-digit scheme. */
    public const PARTICIPANT_IDENTIFIER_PATTERN = '/\A[0-9]{4}:[^\s\p{C}:]+\z/u';

    private function __construct()
    {
    }

    /**
     * Matches a string against a shared log policy, with an optional byte limit.
     *
     * @param  mixed    $value     Candidate value to check against the log policy.
     * @param  string   $pattern   Regular expression defining accepted characters.
     * @param  int|null $maxLength Optional maximum byte length.
     * @return bool     Whether the value is a string that satisfies both constraints.
     */
    public static function matches($value, string $pattern, ?int $maxLength = null): bool
    {
        return is_string($value)
            && ($maxLength === null || strlen($value) <= $maxLength)
            && preg_match($pattern, $value) === 1;
    }
}
