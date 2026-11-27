<?php

namespace DreaM1\EfakturaConnector\Core\Pagination;

use DreaM1\EfakturaConnector\Support\Exceptions\InvalidInputException;

/** Positive number of items requested for one page, within the OpenAPI int32 range. */
final class PageSize
{
    /** Maximum page-size value representable by OpenAPI int32 query parameters. */
    public const MAX_OPENAPI_INT32_VALUE = 2147483647;

    private int $value;

    private function __construct(int $value)
    {
        if ($value < 1) {
            throw new InvalidInputException('Page size must be a positive integer.');
        }

        if ($value > self::MAX_OPENAPI_INT32_VALUE) {
            throw new InvalidInputException('Page size must fit the OpenAPI int32 range.');
        }

        $this->value = $value;
    }

    /** Creates a positive page size. */
    public static function fromInt(int $value): self
    {
        return new self($value);
    }

    /** Returns the numeric page size for API query serialization. */
    public function value(): int
    {
        return $this->value;
    }

    /** Whether both objects represent the same page size. */
    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
