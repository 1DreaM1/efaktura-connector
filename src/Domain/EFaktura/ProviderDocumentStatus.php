<?php

namespace DreaM1\EfakturaConnector\Domain\EFaktura;

use DreaM1\EfakturaConnector\Support\Exceptions\InvalidInputException;
use DreaM1\EfakturaConnector\Support\NonBlankString;

/** A provider document lifecycle status and its supported status vocabulary. */
final class ProviderDocumentStatus
{
    /** Provider document lifecycle status value 'ACCEPTED'. */
    public const ACCEPTED = 'ACCEPTED';
    /** Provider document lifecycle status value 'REJECTED'. */
    public const REJECTED = 'REJECTED';
    /** Provider document lifecycle status value 'RECEIVED_BY_GATEWAY'. */
    public const RECEIVED_BY_GATEWAY = 'RECEIVED_BY_GATEWAY';
    /** Provider document lifecycle status value 'VALIDATING'. */
    public const VALIDATING = 'VALIDATING';
    /** Provider document lifecycle status value 'VALIDATION_FAILED'. */
    public const VALIDATION_FAILED = 'VALIDATION_FAILED';
    /** Provider document lifecycle status value 'QUEUED'. */
    public const QUEUED = 'QUEUED';
    /** Provider document lifecycle status value 'SENDING_TO_ACCESS_POINT'. */
    public const SENDING_TO_ACCESS_POINT = 'SENDING_TO_ACCESS_POINT';
    /** Provider document lifecycle status value 'SENT_TO_ACCESS_POINT'. */
    public const SENT_TO_ACCESS_POINT = 'SENT_TO_ACCESS_POINT';
    /** Provider document lifecycle status value 'DELIVERY_FAILED'. */
    public const DELIVERY_FAILED = 'DELIVERY_FAILED';
    /** Provider document lifecycle status value 'RECEIVED'. */
    public const RECEIVED = 'RECEIVED';
    /** Provider document lifecycle status value 'ACKNOWLEDGED'. */
    public const ACKNOWLEDGED = 'ACKNOWLEDGED';

    /** Provider status values used while processing outbound documents. */
    public const OUTBOUND_STATUSES = [
        self::REJECTED,
        self::RECEIVED_BY_GATEWAY,
        self::VALIDATING,
        self::VALIDATION_FAILED,
        self::QUEUED,
        self::SENDING_TO_ACCESS_POINT,
        self::SENT_TO_ACCESS_POINT,
        self::DELIVERY_FAILED,
    ];

    /** Provider status values returned when tracking sent documents. */
    public const SENT_DOCUMENT_STATUSES = [
        self::RECEIVED_BY_GATEWAY,
        self::VALIDATING,
        self::VALIDATION_FAILED,
        self::QUEUED,
        self::SENDING_TO_ACCESS_POINT,
        self::SENT_TO_ACCESS_POINT,
        self::DELIVERY_FAILED,
    ];

    /** Provider status values returned by document submission. */
    public const SUBMISSION_STATUSES = [
        self::ACCEPTED,
        self::REJECTED,
    ];

    /** Provider status values used for received documents. */
    public const RECEIVED_DOCUMENT_STATUSES = [
        self::RECEIVED,
        self::ACKNOWLEDGED,
    ];

    private string $value;

    private function __construct(string $value)
    {
        $this->value = $value;
    }

    /**
     * Creates a status from a supported provider lifecycle value.
     *
     * @param  string                $status Provider lifecycle status string.
     * @return self                  Typed provider status.
     * @throws InvalidInputException when the status is not supported
     */
    public static function fromString(string $status): self
    {
        if (! self::isSupported($status)) {
            throw new InvalidInputException('Unsupported provider document status.');
        }

        return new self($status);
    }

    /**
     * Preserves a non-blank status returned by the provider, including values not known by this version.
     *
     * @throws InvalidInputException when the provider status is blank
     */
    public static function fromProvider(string $status): self
    {
        if (! NonBlankString::isNotBlank($status)) {
            throw new InvalidInputException('Provider document status must not be empty.');
        }

        return new self($status);
    }

    /** Returns the provider status string. */
    public function value(): string
    {
        return $this->value;
    }

    /**
     * Whether both objects represent the same lifecycle status.
     *
     * @param  self $other Status to compare with this instance.
     * @return bool Whether the status values are equal.
     */
    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    /** Whether this status is used for outbound status-change events. */
    public function isOutboundStatus(): bool
    {
        return self::isOutbound($this->value);
    }

    /** Whether this provider status is part of the vocabulary known by this version. */
    public function isKnown(): bool
    {
        return self::isSupported($this->value);
    }

    /**
     * Whether this is a known provider document lifecycle status.
     *
     * @param  string $status Candidate provider lifecycle status.
     * @return bool   Whether the status appears in a supported status set.
     */
    public static function isSupported(string $status): bool
    {
        return in_array($status, array_merge(
            self::OUTBOUND_STATUSES,
            self::SUBMISSION_STATUSES,
            self::RECEIVED_DOCUMENT_STATUSES
        ), true);
    }

    /**
     * Whether the provider status is supported for an outbound status event.
     *
     * @param  string $status Provider-reported or domain status value.
     * @return bool
     */
    public static function isOutbound(string $status): bool
    {
        return in_array($status, self::OUTBOUND_STATUSES, true);
    }
}
