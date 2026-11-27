<?php

namespace DreaM1\EfakturaConnector\Core\Documents;

use DreaM1\EfakturaConnector\Core\ProviderErrors\ProviderErrorDetail;
use DreaM1\EfakturaConnector\Support\TypedArray;

/** Provider error summary and field details reported for a sent document. */
final class SentDocumentError
{
    private ?string $message;
    /** @var ProviderErrorDetail[] */
    private array $details;

    /**
     * Creates a provider error summary and validates its structured detail values.
     *
     * @param string|null           $message Provider error summary, when supplied.
     * @param ProviderErrorDetail[] $details Structured provider error details.
     */
    public function __construct(?string $message, array $details)
    {
        $details = TypedArray::valuesOfType(
            $details,
            ProviderErrorDetail::class,
            'Sent document error details must be provider error detail values.'
        );

        $this->message = $message;
        $this->details = $details;
    }

    /** Returns the provider error summary, or null when it was omitted. */
    public function message(): ?string
    {
        return $this->message;
    }

    /**
     * Returns structured provider error details in their supplied order.
     *
     * @return ProviderErrorDetail[]
     */
    public function details(): array
    {
        return $this->details;
    }
}
