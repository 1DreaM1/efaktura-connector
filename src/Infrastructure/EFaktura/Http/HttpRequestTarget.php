<?php

namespace DreaM1\EfakturaConnector\Infrastructure\EFaktura\Http;

use DreaM1\EfakturaConnector\Support\Exceptions\InvalidInputException;

/**
 * Resolved HTTP request target composed of a path and its query parameters.
 *
 * @api
 */
final class HttpRequestTarget
{
    private string $path;
    /** @var array<int|string, mixed> */
    private array $query;
    private ?string $resolvedTarget;

    /**
     * @param array<int|string, mixed> $query          Query parameters already merged into the target.
     * @param string|null              $resolvedTarget Original serialized target to preserve for transport requests.
     */
    private function __construct(string $path, array $query = [], ?string $resolvedTarget = null)
    {
        $this->path           = $path;
        $this->query          = $query;
        $this->resolvedTarget = $resolvedTarget;
    }

    /** Creates a target from an already-resolved relative path. */
    public static function fromPath(string $path): self
    {
        return new self($path);
    }

    /** @internal Wraps an already-resolved request target without changing its query serialization. */
    public static function fromResolvedTarget(string $target): self
    {
        $querySeparator = strpos($target, '?');
        $path           = $querySeparator === false ? $target : substr($target, 0, $querySeparator);

        return new self($path, [], $target);
    }

    /**
     * Returns a copy with non-null query parameters merged into the target.
     *
     * @param  array<string, string|int|null> $query Values to merge; null values are omitted.
     * @throws InvalidInputException          when a query value is not a string, integer, or null.
     */
    public function withQuery(array $query): self
    {
        foreach ($query as $value) {
            if ($value !== null && ! is_string($value) && ! is_int($value)) {
                throw new InvalidInputException(
                    'API query parameter values must be strings, integers, or null.'
                );
            }
        }

        // Omit only nulls; values such as 0 and an empty string remain explicit query parameters.
        $query = array_filter($query, static fn ($value): bool => $value !== null);

        if ($query === []) {
            return $this;
        }

        $existingQuery = $this->query;

        if ($this->resolvedTarget !== null) {
            $querySeparator = strpos($this->resolvedTarget, '?');

            if ($querySeparator !== false) {
                parse_str(substr($this->resolvedTarget, $querySeparator + 1), $existingQuery);
            }
        }

        $target                 = clone $this;
        $target->query          = array_replace($existingQuery, $query);
        $target->resolvedTarget = null;

        return $target;
    }

    /** Returns the resolved path without its query string. */
    public function path(): string
    {
        return $this->path;
    }

    /** Returns the path and its RFC 3986 encoded query string. */
    public function toString(): string
    {
        if ($this->resolvedTarget !== null) {
            return $this->resolvedTarget;
        }

        if ($this->query === []) {
            return $this->path;
        }

        return $this->path . '?' . http_build_query($this->query, '', '&', PHP_QUERY_RFC3986);
    }
}
