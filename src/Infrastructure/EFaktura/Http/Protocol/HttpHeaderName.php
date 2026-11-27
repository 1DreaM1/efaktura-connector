<?php

namespace DreaM1\EfakturaConnector\Infrastructure\EFaktura\Http\Protocol;

use DreaM1\EfakturaConnector\Core\Retry\RetryAfter;
use DreaM1\EfakturaConnector\Support\Exceptions\InvalidInputException;

/**
 * Validated HTTP header names with case-insensitive comparison.
 *
 * @api
 */
final class HttpHeaderName
{
    /** HTTP header name 'Accept' used by the connector transport. */
    public const ACCEPT = 'Accept';
    /** HTTP header name 'Authorization' used by the connector transport. */
    public const AUTHORIZATION = 'Authorization';
    /** HTTP header name 'Content-Type' used by the connector transport. */
    public const CONTENT_TYPE = 'Content-Type';
    /** HTTP header name 'Idempotency-Key' used by the connector transport. */
    public const IDEMPOTENCY_KEY = 'Idempotency-Key';
    /** HTTP header name 'X-Integrator-Token' used by the Integrator API. */
    public const INTEGRATOR_TOKEN = 'X-Integrator-Token';
    /** HTTP header name 'X-PDS-Secret' used to sign PDS subject requests. */
    public const PDS_SECRET = 'X-PDS-Secret';
    /** HTTP header name 'X-Peppol-Participant-Id' used by the connector transport. */
    public const PEPPOL_PARTICIPANT_ID = 'X-Peppol-Participant-Id';
    /** HTTP header name 'Retry-After' used by the connector transport. */
    public const RETRY_AFTER = RetryAfter::HEADER_NAME;
    /** HTTP header name 'X-Correlation-Id' used by the connector transport. */
    public const X_CORRELATION_ID = 'X-Correlation-Id';
    /** HTTP header name 'X-Request-Id' used by the connector transport. */
    public const X_REQUEST_ID = 'X-Request-Id';
    /** RFC 9110 token pattern accepted for HTTP field names. */
    private const TOKEN_PATTERN = '/\A[!#$%&\'*+.^_`|~0-9A-Za-z-]+\z/D';

    private string $value;

    private function __construct(string $value)
    {
        $this->value = $value;
    }

    /**
     * Creates a validated HTTP header name.
     *
     * @throws InvalidInputException when the value is not a valid HTTP token.
     */
    public static function fromString(string $value): self
    {
        if (preg_match(self::TOKEN_PATTERN, $value) !== 1) {
            throw new InvalidInputException('HTTP header name must be a valid token.');
        }

        return new self($value);
    }

    /** Creates the canonical Accept header name. */
    public static function accept(): self
    {
        return self::fromString(self::ACCEPT);
    }

    /** Creates the canonical Authorization header name. */
    public static function authorization(): self
    {
        return self::fromString(self::AUTHORIZATION);
    }

    /** Creates the canonical Content-Type header name. */
    public static function contentType(): self
    {
        return self::fromString(self::CONTENT_TYPE);
    }

    /** Creates the canonical Idempotency-Key header name. */
    public static function idempotencyKey(): self
    {
        return self::fromString(self::IDEMPOTENCY_KEY);
    }

    /** Creates the canonical X-Integrator-Token header name. */
    public static function integratorToken(): self
    {
        return self::fromString(self::INTEGRATOR_TOKEN);
    }

    /** Creates the canonical X-PDS-Secret header name. */
    public static function pdsSecret(): self
    {
        return self::fromString(self::PDS_SECRET);
    }

    /** Creates the canonical X-Peppol-Participant-Id header name. */
    public static function peppolParticipantId(): self
    {
        return self::fromString(self::PEPPOL_PARTICIPANT_ID);
    }

    /** Creates the canonical Retry-After header name. */
    public static function retryAfter(): self
    {
        return self::fromString(self::RETRY_AFTER);
    }

    /** Creates the canonical X-Correlation-Id header name. */
    public static function xCorrelationId(): self
    {
        return self::fromString(self::X_CORRELATION_ID);
    }

    /** Creates the canonical X-Request-Id header name. */
    public static function xRequestId(): self
    {
        return self::fromString(self::X_REQUEST_ID);
    }

    /** Returns the original casing for HTTP header serialization. */
    public function value(): string
    {
        return $this->value;
    }

    /** Returns the case-insensitive comparison form of this header name. */
    public function normalized(): string
    {
        return strtolower($this->value);
    }

    /** Compares header names using HTTP's case-insensitive rules. */
    public function equals(self $other): bool
    {
        return $this->isNamed($other->value());
    }

    /** Compares this header name with a string using HTTP's case-insensitive rules. */
    public function isNamed(string $name): bool
    {
        return $this->normalized() === strtolower($name);
    }
}
