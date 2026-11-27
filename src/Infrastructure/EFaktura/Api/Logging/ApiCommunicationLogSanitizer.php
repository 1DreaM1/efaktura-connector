<?php

namespace DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Logging;

use DOMException;
use DreaM1\EfakturaConnector\Core\ProviderErrors\ProviderErrorEnvelope;

/** Redacts API communication data before it is persisted by a host application. */
final class ApiCommunicationLogSanitizer
{
    private ApiRequestLogSanitizer $requestSanitizer;
    private ApiResponseLogSanitizer $responseSanitizer;
    private ProviderErrorLogSanitizer $providerErrorSanitizer;

    /** Builds request, response and provider-error sanitizers using one shared payload policy. */
    public function __construct()
    {
        $payloadSanitizer             = new ApiPayloadLogSanitizer();
        $this->requestSanitizer       = new ApiRequestLogSanitizer($payloadSanitizer);
        $this->responseSanitizer      = new ApiResponseLogSanitizer($payloadSanitizer);
        $this->providerErrorSanitizer = new ProviderErrorLogSanitizer();
    }

    /**
     * Decodes and sanitizes a JSON request body for a communication log.
     *
     * @param  string|null                  $body JSON request body, or null when the request has no body
     * @return array<array-key, mixed>|null Sanitized data, or null when no body was supplied
     * @throws DOMException                 when a DOM operation fails during request-body sanitization.
     */
    public function sanitizeRequestBody(?string $body): ?array
    {
        return $this->requestSanitizer->sanitizeBody($body);
    }

    /**
     * Keeps operational request headers and redacts credentials and idempotency keys.
     *
     * @param  string[]              $headers Header lines in "Name: value" form
     * @return array<string, string>
     */
    public function sanitizeRequestHeaders(array $headers): array
    {
        return $this->requestSanitizer->sanitizeHeaders($headers);
    }

    /**
     * Redacts sensitive values from a decoded provider response body.
     *
     * @param  array<array-key, mixed> $data Decoded provider response body to sanitize.
     * @return array<array-key, mixed>
     * @throws DOMException            when a DOM operation fails during response-body sanitization.
     */
    public function sanitizeResponseBody(array $data): array
    {
        return $this->responseSanitizer->sanitizeBody($data);
    }

    /**
     * Redacts sensitive values from decoded provider response headers.
     *
     * @param  array<array-key, mixed> $headers Decoded provider response headers to sanitize.
     * @return array<array-key, mixed>
     * @throws DOMException            when a DOM operation fails during response-header sanitization.
     */
    public function sanitizeResponseHeaders(array $headers): array
    {
        return $this->responseSanitizer->sanitizeHeaders($headers);
    }

    /**
     * Returns only operational error fields safe to persist in a communication log.
     *
     * @param  ProviderErrorEnvelope $error Parsed provider error envelope
     * @return array<string, mixed>
     */
    public function sanitizeProviderError(ProviderErrorEnvelope $error): array
    {
        return $this->providerErrorSanitizer->sanitize($error);
    }

    /**
     * Returns a correlation ID only when it matches the safe identifier format.
     *
     * @param  string|null $correlationId Provider correlation identifier
     * @return string|null Safe correlation identifier, or null when it is not safe
     */
    public function sanitizeCorrelationId(?string $correlationId): ?string
    {
        return $this->providerErrorSanitizer->sanitizeCorrelationId($correlationId);
    }
}
