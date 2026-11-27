<?php

namespace DreaM1\EfakturaConnector\Infrastructure\EFaktura\Metrics;

/**
 * Receives low-cardinality connector metrics without request data or credentials.
 *
 * @api
 */
interface ConnectorMetricsInterface
{
    /** Outcome label for an HTTP response in the 1xx range. */
    public const API_INFORMATIONAL = 'informational';
    /** Outcome label for an HTTP response in the 2xx range. */
    public const API_SUCCESS = 'success';
    /** Outcome label for an HTTP response in the 3xx range. */
    public const API_REDIRECTION = 'redirection';
    /** Outcome label for an HTTP response in the 4xx range other than rate limiting. */
    public const API_CLIENT_ERROR = 'client_error';
    /** Outcome label for an HTTP response in the 5xx range. */
    public const API_SERVER_ERROR = 'server_error';
    /** Outcome label for an HTTP 429 response. */
    public const API_RATE_LIMITED = 'rate_limited';
    /** Outcome label when no HTTP response was received because the connection failed. */
    public const API_CONNECTION_ERROR = 'connection_error';
    /** Outcome label when the response exceeded the configured size limit. */
    public const API_RESPONSE_TOO_LARGE = 'response_too_large';

    /** Outcome label when an open circuit breaker rejected the request before any HTTP attempt. */
    public const API_CIRCUIT_OPEN = 'circuit_open';

    /** Outcome label when provider backpressure deferred the request before any HTTP attempt. */
    public const API_DEFERRED = 'deferred';

    /** Token event label for a process-local token hit. */
    public const TOKEN_MEMORY_HIT = 'memory_hit';
    /** Token event label for a persistent cache hit. */
    public const TOKEN_CACHE_HIT = 'cache_hit';
    /** Token event label for a persistent cache miss. */
    public const TOKEN_CACHE_MISS = 'cache_miss';
    /** Token event label for a cached token that has expired. */
    public const TOKEN_CACHE_EXPIRED = 'cache_expired';
    /** Token event label for a successful OAuth token refresh. */
    public const TOKEN_REFRESH_SUCCESS = 'refresh_success';
    /** Token event label for a failed OAuth token refresh. */
    public const TOKEN_REFRESH_FAILURE = 'refresh_failure';

    /** Outcome label for a successful Schematron validation run. */
    public const SCHEMATRON_SUCCESS = 'success';
    /** Outcome label for a failed Schematron validation run. */
    public const SCHEMATRON_FAILURE = 'failure';

    /**
     * Records one HTTP attempt; request URL, path, headers and body are intentionally omitted.
     *
     * @param string   $method          HTTP method used for the attempt.
     * @param int|null $statusCode      HTTP response status, or null when no response was received.
     * @param float    $durationSeconds Duration of the attempt in seconds.
     * @param string   $outcome         One of the API_* outcome labels.
     */
    public function recordApiRequest(
        string $method,
        ?int $statusCode,
        float $durationSeconds,
        string $outcome
    ): void;

    /**
     * Records a fixed token memory/cache/refresh event without exposing the cache key or token.
     *
     * @param string $event One of the TOKEN_* event labels.
     */
    public function recordTokenCacheEvent(string $event): void;

    /**
     * Records a Schematron batch duration and number of rule sets, without document content.
     *
     * @param float  $durationSeconds Duration of the validation run in seconds.
     * @param string $outcome         One of the SCHEMATRON_* outcome labels.
     * @param int    $rulesetCount    Number of rulesets included in the run.
     */
    public function recordSchematronValidation(
        float $durationSeconds,
        string $outcome,
        int $rulesetCount
    ): void;
}
