<?php

namespace DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Logging;

use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Authentication\OAuthAuthorizationScheme;

/** Removes credential and bearer-token values from text before it is logged. */
class SensitiveDataRedactor
{
    /** Replacement marker written in place of redacted sensitive data. */
    public const REDACTED_VALUE = '[redacted]';

    /**
     * Redacts values assigned to common credential keys and any embedded bearer tokens.
     *
     * @param  string $text Text value to read, write, or validate.
     * @return string
     */
    public static function redactCredentials(string $text): string
    {
        $text = self::redactBearerTokens($text);

        // Match common credential field names in JSON-like or key=value text while leaving surrounding syntax intact.
        $regex = '/(?<prefix>["\']?\b' . SensitiveFieldPolicy::textFieldNamePattern() . '\b["\']?\s*(?<separator>[:=])\s*)'
            . '(?<value>"(?:\\\\.|[^"\\\\])*"|\'(?:\\\\.|[^\'\\\\])*\'|[^\s,;}\]]+)/i';

        $redactor = static function (array $matches): string {
            $value = $matches['value'];
            // Retain matching quotes for valid JSON while replacing only the credential value.
            $quote         = $value[0] === '"' || $value[0] === "'" ? $value[0] : '';
            $redactedValue = $quote === ''
                ? self::REDACTED_VALUE
                : $quote . self::REDACTED_VALUE . $quote;

            // In key=value text an unquoted value ends at '&' (a literal '&' is percent-encoded there), so
            // following form or query fields stay readable. After ':' the whole value is redacted instead.
            $suffix = '';

            if ($quote === '' && $matches['separator'] === '=') {
                $ampersand = strpos($value, '&');

                if ($ampersand !== false) {
                    // The remainder may hold further credential fields, so it is redacted in turn.
                    $suffix = self::redactCredentials(substr($value, $ampersand));
                }
            }

            return $matches['prefix'] . $redactedValue . $suffix;
        };

        return (string) preg_replace_callback($regex, $redactor, $text);
    }

    /**
     * Redacts URL components that commonly carry signed or otherwise sensitive values.
     *
     * @param  string $url URL value to retain safely in a log.
     * @return string
     */
    public static function redactUrl(string $url): string
    {
        $url = (string) preg_replace(
            '/\A([a-z][a-z0-9+.-]*:\/\/)[^\/?#@]*@/i',
            '$1' . self::REDACTED_VALUE . '@',
            $url,
            1
        );
        $url = (string) preg_replace('/\?[^#]*/', '?' . self::REDACTED_VALUE, $url, 1);
        $url = (string) preg_replace('/#.*\z/s', '#' . self::REDACTED_VALUE, $url, 1);

        return self::redactCredentials($url);
    }

    /**
     * Replaces OAuth bearer-token values while preserving the Authorization scheme.
     *
     * @param  string $text Text value to read, write, or validate.
     * @return string
     */
    public static function redactBearerTokens(string $text): string
    {
        // Preserve the OAuth scheme for diagnostics, but never retain the token bytes.
        $scheme  = preg_quote(OAuthAuthorizationScheme::BEARER, '/');
        $pattern = '/' . $scheme . '\s+[A-Za-z0-9._~+\/-]+=*/i';

        return (string) preg_replace(
            $pattern,
            OAuthAuthorizationScheme::BEARER
                . ' ' . self::REDACTED_VALUE,
            $text
        );
    }
}
