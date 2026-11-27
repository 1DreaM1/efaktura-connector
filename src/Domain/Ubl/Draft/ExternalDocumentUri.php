<?php

namespace DreaM1\EfakturaConnector\Domain\Ubl\Draft;

use DreaM1\EfakturaConnector\Support\Exceptions\InvalidInputException;
use DreaM1\EfakturaConnector\Support\StringValue;

/**
 * An absolute http or https URI locating an external UBL document attachment.
 *
 * Other schemes are refused: the URI is written to the invoice and a receiving application may render it as a
 * link, so `javascript:`, `data:` or `file:` locations must not be producible by this package.
 */
final class ExternalDocumentUri
{
    use StringValue;

    /** URI schemes an external document location may use. */
    private const ALLOWED_SCHEMES = ['http', 'https'];

    protected static function normalize(string $value): string
    {
        $parts = parse_url($value);

        if (
            trim($value) === ''
            || preg_match('/[\x00-\x20<>"{}|\\\\^`]/', $value) === 1
            || preg_match('/%(?![A-Fa-f0-9]{2})/', $value) === 1
            || $parts === false
            || ! isset($parts['scheme'])
            || preg_match('/\A[A-Za-z][A-Za-z0-9+.-]*\z/', $parts['scheme']) !== 1
            || (
                preg_match('/\A[A-Za-z][A-Za-z0-9+.-]*:\/\//', $value) === 1
                && (! isset($parts['host']) || $parts['host'] === '')
            )
        ) {
            throw new InvalidInputException('An absolute external document URI is required.');
        }

        if (! in_array(strtolower($parts['scheme']), self::ALLOWED_SCHEMES, true)) {
            throw new InvalidInputException('An external document URI must use the http or https scheme.');
        }

        if (! isset($parts['host']) || $parts['host'] === '') {
            throw new InvalidInputException('An external document URI must name a host.');
        }

        return $value;
    }
}
