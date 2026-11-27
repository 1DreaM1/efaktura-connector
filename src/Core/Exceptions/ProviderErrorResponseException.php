<?php

namespace DreaM1\EfakturaConnector\Core\Exceptions;

use DreaM1\EfakturaConnector\Core\ProviderErrors\ProviderErrorEnvelope;
use Throwable;

/**
 * The provider answered, but not with 2xx. Carries its status and body, plus response headers supplied by transport.
 *
 * The API boundary may attach a recognized ProviderErrorEnvelope without changing this raw response.
 * The host application decides how to classify and present the parsed error.
 * This exception is intentionally extensible.
 */
class ProviderErrorResponseException extends ProviderApiException
{
    private string $body;
    private ?ProviderErrorEnvelope $errorEnvelope;

    /**
     * Creates a new ProviderErrorResponseException value.
     * @param int                        $statusCode      HTTP status code received from the provider, when available.
     * @param string                     $body            Raw HTTP response body received from the provider.
     * @param array<string, string>      $responseHeaders HTTP response headers retained from the provider.
     * @param Throwable|null             $previous        Previous exception that caused this failure, when available.
     * @param ProviderErrorEnvelope|null $errorEnvelope   Parsed error response, when the API boundary recognized one.
     */
    public function __construct(
        int $statusCode,
        string $body = '',
        array $responseHeaders = [],
        ?Throwable $previous = null,
        ?ProviderErrorEnvelope $errorEnvelope = null
    ) {
        parent::__construct('Provider answered with HTTP ' . $statusCode . '.', $statusCode, $responseHeaders, $previous);
        $this->body          = $body;
        $this->errorEnvelope = $errorEnvelope;
    }

    /**
     * Response body exactly as received.
     *
     * @return string
     */
    public function body(): string
    {
        return $this->body;
    }

    /**
     * Parsed provider error envelope supplied by the API boundary, or null when it was not recognized.
     *
     * @return ProviderErrorEnvelope|null
     */
    public function errorEnvelope(): ?ProviderErrorEnvelope
    {
        return $this->errorEnvelope;
    }
}
