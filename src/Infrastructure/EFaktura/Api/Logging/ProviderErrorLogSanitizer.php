<?php

namespace DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Logging;

use DreaM1\EfakturaConnector\Core\ProviderErrors\ProviderErrorDetail;
use DreaM1\EfakturaConnector\Core\ProviderErrors\ProviderErrorEnvelope;

/** Retains only safe operational fields from provider errors. @internal */
final class ProviderErrorLogSanitizer
{
    /** Pattern for safe provider field paths with optional array indexes. */
    private const SAFE_PROVIDER_FIELD_PATTERN = '/\A\/?[A-Za-z_][A-Za-z0-9_.:-]*'
        . '(?:\[\d+])?(?:\/[A-Za-z_][A-Za-z0-9_.:-]*(?:\[\d+])?)*\z/';
    /** Pattern for stable uppercase validation rule identifiers. */
    private const RULE_IDENTIFIER_PATTERN = '/\A[A-Z][A-Z0-9]*(?:-[A-Z0-9]+)+\z/';
    /** Maximum length of a provider value retained in an error log. */
    private const MAX_SAFE_PROVIDER_VALUE_LENGTH = 128;
    /** Maximum length of a provider field path retained in an error log. */
    private const MAX_PROVIDER_FIELD_LENGTH = 256;
    /**
     * Returns a safe diagnostic shape with free-form provider text redacted.
     *
     * @param  ProviderErrorEnvelope $error Provider or validation error associated with the result.
     * @return array<string, mixed>
     */
    public function sanitize(ProviderErrorEnvelope $error): array
    {
        $safe = [];

        $category = $error->category();

        if ($category !== null && $category->isKnown()) {
            $safe['category'] = $category->value();
        }

        $code = $error->code();

        if ($code !== null && $this->isSafeProviderCode($code->value())) {
            $safe['code'] = $code->value();
        }

        // Provider messages are free text and may contain invoice or personal data.
        $safe['message'] = SensitiveDataRedactor::REDACTED_VALUE;

        if ($error->hasDetails()) {
            $details         = $error->details();
            $safe['details'] = $this->sanitizeDetails($details);

            if (count($details) > ApiLogSanitizationPolicy::MAX_ARRAY_ITEMS) {
                $safe['details_truncated'] = true;
            }
        }

        $retryable = $error->retryable();

        if ($retryable !== null) {
            $safe['retryable'] = $retryable;
        }

        $correlationId = $error->correlationId();

        if ($this->isSafeIdentifier($correlationId)) {
            $safe['correlation_id'] = $correlationId;
        }

        return $safe;
    }

    /**
     * Returns an identifier only when it matches the safe format.
     *
     * @param  string|null $correlationId Provider correlation identifier for the request.
     * @return string|null
     */
    public function sanitizeCorrelationId(?string $correlationId): ?string
    {
        return $this->isSafeIdentifier($correlationId) ? $correlationId : null;
    }

    /**
     * @param  ProviderErrorDetail[]             $details Structured details associated with the provider error.
     * @return array<int, array<string, string>>
     */
    private function sanitizeDetails(array $details): array
    {
        $safe = [];

        foreach (array_slice($details, 0, ApiLogSanitizationPolicy::MAX_ARRAY_ITEMS, true) as $key => $detail) {
            $safeDetail = [];

            $field = $detail->field();
            $issue = $detail->issue();
            $value = $detail->value();

            if ($field !== null) {
                $safeDetail['field'] = $this->isSafeProviderField($field)
                    ? $field
                    : SensitiveDataRedactor::REDACTED_VALUE;
            }

            if ($issue !== null) {
                // Free-form issue text can echo submitted values, so redact it even when a safe field/code is kept.
                $safeDetail['issue'] = SensitiveDataRedactor::REDACTED_VALUE;
            }

            if ($value !== null) {
                // Keep only a recognized rule code that is explicitly identified by the corresponding issue.
                $safeDetail['value'] = $this->isRuleIdentifier($value, $issue)
                    ? $value
                    : SensitiveDataRedactor::REDACTED_VALUE;
            }

            if ($safeDetail !== []) {
                $safe[$key] = $safeDetail;
            }
        }

        return $safe;
    }

    /** Keeps provider codes only when they match the restricted diagnostic format. */
    private function isSafeProviderCode(?string $value): bool
    {
        return ApiLogValuePolicy::matches(
            $value,
            ApiLogValuePolicy::SAFE_PROVIDER_CODE_PATTERN,
            self::MAX_SAFE_PROVIDER_VALUE_LENGTH
        );
    }

    /** Keeps bounded identifiers that contain only the accepted identifier characters. */
    private function isSafeIdentifier(?string $value): bool
    {
        return ApiLogValuePolicy::matches(
            $value,
            ApiLogValuePolicy::SAFE_IDENTIFIER_PATTERN,
            self::MAX_SAFE_PROVIDER_VALUE_LENGTH
        );
    }

    /** Keeps provider field paths only when they match the supported path syntax. */
    private function isSafeProviderField(string $field): bool
    {
        return ApiLogValuePolicy::matches(
            $field,
            self::SAFE_PROVIDER_FIELD_PATTERN,
            self::MAX_PROVIDER_FIELD_LENGTH
        );
    }

    /** Retains a rule code only when it is embedded in the corresponding issue text. */
    private function isRuleIdentifier(string $value, ?string $issue): bool
    {
        if (
            $issue === null
            || preg_match(self::RULE_IDENTIFIER_PATTERN, $value) !== 1
        ) {
            return false;
        }

        return strpos(ltrim($issue), '[' . $value . ']') === 0;
    }
}
