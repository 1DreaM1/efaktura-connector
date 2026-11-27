<?php

namespace DreaM1\EfakturaConnector\Core\Exceptions;

use DreaM1\EfakturaConnector\Core\Retry\RetryDeadline;

/**
 * Host-facing reading of a connector failure: what happened and what the host may safely do next.
 *
 * Produced by FailureClassifier. It is a hint for job and queue handling and carries no user-facing text.
 * Retryable never means "repeat blindly": when requestMayHaveReachedProvider() is true, a document submission
 * must be repeated with the same idempotency key and payload, or its outcome checked through tracking first.
 *
 * @api
 */
final class FailureAssessment
{
    /** The OAuth credentials, token or scope were refused; repeating cannot help until they are fixed. */
    public const CATEGORY_AUTHENTICATION = 'authentication';

    /** The request was held locally by backpressure or an open circuit breaker; retry later. */
    public const CATEGORY_DEFERRED = 'deferred';

    /** The provider could not be reached or answered with a temporary failure. */
    public const CATEGORY_TRANSIENT = 'transient';

    /** The provider or a remote preflight check refused the request or document; the input must change. */
    public const CATEGORY_REJECTED = 'rejected';

    /** The provider answered successfully, but the answer is not readable; the outcome must be verified. */
    public const CATEGORY_INVALID_RESPONSE = 'invalid_response';

    /** A caller value, document or request body is invalid; nothing was sent. */
    public const CATEGORY_INVALID_INPUT = 'invalid_input';

    /** The connector is not configured or local infrastructure is missing; an administrator must fix it. */
    public const CATEGORY_CONFIGURATION = 'configuration';

    /** A broken internal invariant or an unrecognized failure; not a condition to retry. */
    public const CATEGORY_INTERNAL = 'internal';

    private string $category;
    private bool $retryable;
    private bool $requestMayHaveReachedProvider;
    private bool $outcomeUnknown;
    private ?RetryDeadline $retryAt;

    /**
     * @param string             $category                      One of the CATEGORY_* constants.
     * @param bool               $retryable                     Whether repeating the operation can succeed without changes.
     * @param bool               $requestMayHaveReachedProvider Whether the provider may have received the request.
     * @param bool               $outcomeUnknown                Whether the provider may have acted without the host learning it.
     * @param RetryDeadline|null $retryAt                       Earliest advised retry time, when the provider or backpressure gave one.
     */
    public function __construct(
        string $category,
        bool $retryable,
        bool $requestMayHaveReachedProvider,
        bool $outcomeUnknown,
        ?RetryDeadline $retryAt = null
    ) {
        $this->category                      = $category;
        $this->retryable                     = $retryable;
        $this->requestMayHaveReachedProvider = $requestMayHaveReachedProvider;
        $this->outcomeUnknown                = $outcomeUnknown;
        $this->retryAt                       = $retryAt;
    }

    /** Returns one of the CATEGORY_* constants. */
    public function category(): string
    {
        return $this->category;
    }

    /** Tells whether repeating the same operation can succeed without changing its input or configuration. */
    public function isRetryable(): bool
    {
        return $this->retryable;
    }

    /** Tells whether the provider may have received the request before the failure. */
    public function requestMayHaveReachedProvider(): bool
    {
        return $this->requestMayHaveReachedProvider;
    }

    /**
     * Tells whether the provider may have processed the request without the host learning the result.
     *
     * For a document submission, verify through tracking or repeat with the same idempotency key.
     */
    public function isOutcomeUnknown(): bool
    {
        return $this->outcomeUnknown;
    }

    /** Tells whether the operation can be repeated without risk of a duplicate side effect. */
    public function isSafeToRepeatBlindly(): bool
    {
        return $this->retryable && ! $this->requestMayHaveReachedProvider;
    }

    /** Tells whether credentials, scope or configuration need attention before any retry. */
    public function needsAdministratorAttention(): bool
    {
        return $this->category === self::CATEGORY_AUTHENTICATION
            || $this->category === self::CATEGORY_CONFIGURATION;
    }

    /** Returns the earliest advised retry time, or null when no time was advised. */
    public function retryAt(): ?RetryDeadline
    {
        return $this->retryAt;
    }
}
