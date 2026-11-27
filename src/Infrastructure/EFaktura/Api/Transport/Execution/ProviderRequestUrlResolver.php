<?php

namespace DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Transport\Execution;

use DreaM1\EfakturaConnector\Core\Configuration\ProviderBaseUrl;
use DreaM1\EfakturaConnector\Support\Exceptions\InvalidInputException;
use DreaM1\EfakturaConnector\Support\Exceptions\InvariantViolationException;
use DreaM1\EfakturaConnector\Support\HttpsUrlValidator;

/** Builds the validated absolute URL immediately before a provider request is sent. @internal */
final class ProviderRequestUrlResolver
{
    /**
     * Resolves the request path against its configured provider base URL.
     *
     * @param  ProviderBaseUrl|null        $baseUrl Configured provider base URL.
     * @param  string                      $path    Provider-relative request path, including its query string.
     * @return string                      Validated absolute HTTPS URL.
     * @throws InvariantViolationException when the request has no configured provider base URL.
     * @throws InvalidInputException       when the path or resulting URL is unsafe.
     */
    public static function resolve(?ProviderBaseUrl $baseUrl, string $path): string
    {
        if ($baseUrl === null) {
            throw new InvariantViolationException('Request base URL has not been configured yet.');
        }

        if (
            $path === ''
            || $path[0] !== '/'
            || strncmp($path, '//', 2) === 0
            || strpos($path, '#') !== false
            || preg_match('/[\\x00-\\x20\\x7F]/', $path) === 1
        ) {
            throw new InvalidInputException('HTTP request path must be a safe provider-relative path.');
        }

        $url = $baseUrl->value() . $path;

        if (! HttpsUrlValidator::isAbsoluteHttpsUrl($url)) {
            throw new InvalidInputException('HTTP request URL must be an absolute HTTPS URL.');
        }

        return $url;
    }
}
