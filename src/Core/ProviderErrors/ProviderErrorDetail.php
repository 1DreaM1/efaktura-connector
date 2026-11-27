<?php

namespace DreaM1\EfakturaConnector\Core\ProviderErrors;

use DreaM1\EfakturaConnector\Support\ScalarArrayValue;

/** One detail in the provider's error envelope: { field?, issue?, value? }. */
final class ProviderErrorDetail
{
    /** @var array<string, mixed> Provider-supplied fields for this error detail. */
    private array $data;

    /**
     * Restricts construction to the provider error detail parser.
     *
     * @param array<string, mixed> $data Provider-supplied fields for this error detail.
     */
    private function __construct(array $data)
    {
        $this->data = $data;
    }

    /**
     * Creates a value from the supplied array data.
     * @param  array<string, mixed> $data Provider-supplied fields for one error detail.
     * @return self
     */
    public static function fromArray(array $data): self
    {
        return new self($data);
    }

    /**
     * Returns the field path identified by this detail, or null when omitted.
     *
     * @return string|null
     */
    public function field(): ?string
    {
        return ScalarArrayValue::asString($this->data, 'field');
    }

    /**
     * Returns the provider explanation for this field detail, or null when omitted.
     *
     * @return string|null
     */
    public function issue(): ?string
    {
        return ScalarArrayValue::asString($this->data, 'issue');
    }

    /**
     * Returns the provider value associated with this field detail, or null when omitted.
     *
     * @return string|null
     */
    public function value(): ?string
    {
        return ScalarArrayValue::asString($this->data, 'value');
    }

    /**
     * The detail exactly as received.
     *
     * @return array<string, mixed> Detail fields exactly as received.
     */
    public function toArray(): array
    {
        return $this->data;
    }
}
