<?php

namespace DreaM1\EfakturaConnector\Support;

use DreaM1\EfakturaConnector\Support\Exceptions\InvalidInputException;

/**
 * A valid HTTP status code from RFC 9110.
 *
 * @api
 */
final class HttpStatusCode
{
    /** HTTP 400 Bad Request. */
    public const BAD_REQUEST = 400;
    /** HTTP 500 Internal Server Error. */
    public const INTERNAL_SERVER_ERROR = 500;
    /** HTTP 300 Multiple Choices. */
    public const MULTIPLE_CHOICES = 300;
    /** HTTP 204 No Content. */
    public const NO_CONTENT = 204;
    /** HTTP 404 Not Found. */
    public const NOT_FOUND = 404;
    /** HTTP 200 OK. */
    public const OK = 200;
    /** HTTP 429 Too Many Requests. */
    public const TOO_MANY_REQUESTS = 429;
    /** HTTP 401 Unauthorized. */
    public const UNAUTHORIZED = 401;
    /** HTTP 422 Unprocessable Content. */
    public const UNPROCESSABLE_ENTITY = 422;
    /** Lowest numeric value allowed for an HTTP status code. */
    private const MIN_STATUS_CODE = 100;
    /** Highest numeric value allowed for an HTTP status code. */
    private const MAX_STATUS_CODE = 599;
    /** Exclusive end bound used by range checks for valid status codes. */
    private const STATUS_CODE_RANGE_END_EXCLUSIVE = self::MAX_STATUS_CODE + 1;

    private int $value;

    private function __construct(int $value)
    {
        $this->value = $value;
    }

    /**
     * Creates a valid HTTP status code, including codes not assigned by IANA.
     *
     * @param  int                   $value HTTP status code from 100 through 599.
     * @return self
     * @throws InvalidInputException when the value is outside the valid HTTP range.
     */
    public static function fromInt(int $value): self
    {
        if ($value < self::MIN_STATUS_CODE || $value > self::MAX_STATUS_CODE) {
            throw new InvalidInputException('An HTTP status code must be between 100 and 599.');
        }

        return new self($value);
    }

    /** Creates a status code when the integer is in the valid HTTP range. */
    public static function tryFromInt(int $value): ?self
    {
        return $value >= self::MIN_STATUS_CODE && $value <= self::MAX_STATUS_CODE ? new self($value) : null;
    }

    /** Returns the exact numeric status code. */
    public function value(): int
    {
        return $this->value;
    }

    /** Whether the code belongs to the informational response class. */
    public function isInformational(): bool
    {
        return $this->value >= self::MIN_STATUS_CODE && $this->value < self::OK;
    }

    /** Whether the code belongs to the successful response class. */
    public function isSuccessful(): bool
    {
        return $this->value >= self::OK && $this->value < self::MULTIPLE_CHOICES;
    }

    /** Whether the code belongs to the redirection response class. */
    public function isRedirection(): bool
    {
        return $this->value >= self::MULTIPLE_CHOICES && $this->value < self::BAD_REQUEST;
    }

    /** Whether the code belongs to the client-error response class. */
    public function isClientError(): bool
    {
        return $this->value >= self::BAD_REQUEST && $this->value < self::INTERNAL_SERVER_ERROR;
    }

    /** Whether the code belongs to the 5xx server-error class. */
    public function isServerError(): bool
    {
        return $this->value >= self::INTERNAL_SERVER_ERROR && $this->value < self::STATUS_CODE_RANGE_END_EXCLUSIVE;
    }

    /** Whether the code is HTTP 204 No Content. */
    public function isNoContent(): bool
    {
        return $this->value === self::NO_CONTENT;
    }

    /** Whether the code is HTTP 429 Too Many Requests. */
    public function isTooManyRequests(): bool
    {
        return $this->value === self::TOO_MANY_REQUESTS;
    }

    /** Whether the code is HTTP 401 Unauthorized. */
    public function isUnauthorized(): bool
    {
        return $this->value === self::UNAUTHORIZED;
    }

    /** Whether the code is HTTP 404 Not Found. */
    public function isNotFound(): bool
    {
        return $this->value === self::NOT_FOUND;
    }

    /** Whether both objects represent the same numeric status code. */
    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
