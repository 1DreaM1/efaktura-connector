<?php

namespace DreaM1\EfakturaConnector\Core\ProviderErrors;

use DreaM1\EfakturaConnector\Support\ScalarArrayValue;

/**
 * Parsed SAPI / Peppol Gateway error envelope:
 * { "error": { "category", "code", "message", "details", "retryable", "correlation_id" } }.
 *
 * The original HTTP response body remains available on ProviderErrorResponseException. Unlike the
 * strict schema-aware API response reader used for successful API responses, error bodies are read
 * tolerantly: partial envelopes and unknown fields are preserved without changing their text.
 */
final class ProviderErrorEnvelope
{
    /** @var array<string, mixed> Parsed fields from the provider's error object. */
    private array $data;

    /**
     * Restricts construction to the provider error parsing factories.
     *
     * @param array<string, mixed> $data Parsed fields from the provider's error object.
     */
    private function __construct(array $data)
    {
        $this->data = $data;
    }

    /**
     * Creates a value when the body contains an error object with a string message; otherwise returns null.
     *
     * @param  array<array-key, mixed> $body decoded response body
     * @return self|null
     */
    public static function fromArray(array $body): ?self
    {
        $error = $body['error'] ?? null;

        // Require a string message before exposing parsed fields; otherwise callers can use the raw body.
        if (! is_array($error) || ! is_string($error['message'] ?? null)) {
            return null;
        }

        return new self($error);
    }

    /**
     * Returns the provider error category, or null when it was omitted.
     *
     * @return ProviderErrorCategory|null
     */
    public function category(): ?ProviderErrorCategory
    {
        $category = ScalarArrayValue::asString($this->data, 'category');

        return $category === null ? null : ProviderErrorCategory::fromString($category);
    }

    /**
     * Returns the provider error code, or null when it was omitted or cannot be read as a scalar value.
     *
     * @return ProviderErrorCode|null
     */
    public function code(): ?ProviderErrorCode
    {
        $code = ScalarArrayValue::asString($this->data, 'code');

        return $code === null ? null : ProviderErrorCode::fromString($code);
    }

    /**
     * Returns the message.
     *
     * @return string
     */
    public function message(): string
    {
        return (string) ScalarArrayValue::asString($this->data, 'message');
    }

    /**
     * Returns the field-level details supplied in the provider error envelope.
     * @return ProviderErrorDetail[]
     */
    public function details(): array
    {
        $detailData = $this->data['details'] ?? null;
        $details    = [];

        // Error parsing is tolerant: keep valid detail objects and ignore malformed siblings.
        foreach (is_array($detailData) ? $detailData : [] as $detail) {
            if (is_array($detail)) {
                $details[] = ProviderErrorDetail::fromArray($detail);
            }
        }

        return $details;
    }

    /**
     * Whether the response supplied a details array, including an empty one.
     *
     * @return bool
     */
    public function hasDetails(): bool
    {
        return is_array($this->data['details'] ?? null);
    }

    /**
     * Returns the provider retryability flag, or null when the response omitted it.
     *
     * @return bool|null
     */
    public function retryable(): ?bool
    {
        $retryable = $this->data['retryable'] ?? null;

        return is_bool($retryable) ? $retryable : null;
    }

    /**
     * Provider's ID of the failed call; quote it when reporting a problem to the provider.
     *
     * @return string|null
     */
    public function correlationId(): ?string
    {
        return ScalarArrayValue::asString($this->data, 'correlation_id');
    }

    /**
     * The error object exactly as received, including fields the schema does not document.
     *
     * @return array<string, mixed> Parsed provider error fields, including unrecognized fields.
     */
    public function toArray(): array
    {
        return $this->data;
    }
}
