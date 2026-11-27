<?php

namespace DreaM1\EfakturaConnector\Infrastructure\EFaktura\Http\Protocol;

use DreaM1\EfakturaConnector\Support\Exceptions\InvalidInputException;
use DreaM1\EfakturaConnector\Support\StringValue;

/**
 * HTTP method names used by the provider endpoint clients.
 *
 * @api
 */
final class HttpMethod
{
    use StringValue;

    /** HTTP request method 'DELETE' supported by the connector transport. */
    public const DELETE = 'DELETE';
    /** HTTP request method 'GET' supported by the connector transport. */
    public const GET = 'GET';
    /** HTTP request method 'PATCH' supported by the connector transport. */
    public const PATCH = 'PATCH';
    /** HTTP request method 'PUT' supported by the connector transport. */
    public const PUT = 'PUT';
    /** HTTP request method 'POST' supported by the connector transport. */
    public const POST = 'POST';
    /** HTTP token pattern accepted for method values. */
    private const PATTERN = '/\A[!#$%&\'*+\-.^_`|~0-9A-Za-z]+\z/';

    /** Creates the canonical GET method. */
    public static function get(): self
    {
        return self::fromString(self::GET);
    }

    /** Creates the canonical POST method. */
    public static function post(): self
    {
        return self::fromString(self::POST);
    }

    /** Creates the canonical PATCH method. */
    public static function patch(): self
    {
        return self::fromString(self::PATCH);
    }

    /** Creates the canonical PUT method. */
    public static function put(): self
    {
        return self::fromString(self::PUT);
    }

    /** Creates the canonical DELETE method. */
    public static function delete(): self
    {
        return self::fromString(self::DELETE);
    }

    /** Whether the method is the exact GET verb, which is retry-safe by default. */
    public function isGet(): bool
    {
        return $this->value === self::GET;
    }

    protected static function normalize(string $value): string
    {
        if (preg_match(self::PATTERN, $value) !== 1) {
            throw new InvalidInputException('HTTP method must be a valid token.');
        }

        return $value;
    }
}
