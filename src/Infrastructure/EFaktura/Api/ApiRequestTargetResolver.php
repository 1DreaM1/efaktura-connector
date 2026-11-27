<?php

namespace DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api;

use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Http\HttpRequestTarget;
use DreaM1\EfakturaConnector\Support\Exceptions\InvalidInputException;
use DreaM1\EfakturaConnector\Support\NonBlankString;

/** @internal Resolves provider API path templates into transport-neutral request targets. */
final class ApiRequestTargetResolver
{
    /** Prevents instantiation of this API path resolver. */
    private function __construct()
    {
    }

    /**
     * Resolves a route template, encoding each supplied value as one path segment.
     *
     * @param  array<string, mixed>  $parameters Values for the template placeholders; only strings and integers are accepted.
     * @throws InvalidInputException when parameters do not match the template or an identifier is invalid.
     */
    public static function fromTemplate(string $template, array $parameters = []): HttpRequestTarget
    {
        $usedParameters = [];
        $path           = preg_replace_callback(
            '/\{(\w+)}/',
            static function (array $match) use ($parameters, &$usedParameters): string {
                $name = $match[1];

                if (! array_key_exists($name, $parameters)) {
                    throw new InvalidInputException('API path parameters must match the path template.');
                }

                $parameter = $parameters[$name];

                if (! is_string($parameter) && ! is_int($parameter)) {
                    throw new InvalidInputException('API path identifiers must be strings or integers.');
                }

                $value = (string) $parameter;
                NonBlankString::assertNotBlank($value, 'API path identifiers must not be empty.');

                if ($value === '.' || $value === '..') {
                    throw new InvalidInputException('API path identifiers must not be dot segments.');
                }

                $usedParameters[$name] = true;

                return rawurlencode($value);
            },
            $template
        );

        if (count($usedParameters) !== count($parameters)) {
            throw new InvalidInputException('API path parameters must match the path template.');
        }

        return HttpRequestTarget::fromPath((string) $path);
    }
}
