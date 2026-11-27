<?php

namespace DreaM1\EfakturaConnector\Core\MessageStatuses;

use DreaM1\EfakturaConnector\Domain\EFaktura\MessageLevelStatusCode;
use DreaM1\EfakturaConnector\Support\Exceptions\InvalidInputException;
use DreaM1\EfakturaConnector\Support\TypedArray;

/** Response code, description and reasons reported for one message-level status; the code is optional in responses. */
final class ProviderMessageLevelStatusResult
{
    private ?MessageLevelStatusCode $code;
    private ?string $description;
    /** @var list<ProviderMessageLevelStatusReason> */
    private array $reasons;

    /**
     * Creates the result part of a message-level status.
     *
     * @param  MessageLevelStatusCode|null        $code        Provider response code for the message status.
     * @param  string|null                        $description Description reported for the provider response.
     * @param  ProviderMessageLevelStatusReason[] $reasons     Provider-reported reasons associated with the status.
     * @throws InvalidInputException              when a reason is not a ProviderMessageLevelStatusReason
     */
    public function __construct(?MessageLevelStatusCode $code = null, ?string $description = null, array $reasons = [])
    {
        $this->code        = $code;
        $this->description = $description;
        $this->reasons     = TypedArray::valuesOfType(
            $reasons,
            ProviderMessageLevelStatusReason::class,
            'Message-level status reasons must contain reason values.'
        );
    }

    /** Returns the response code, or null when the provider omitted it. */
    public function code(): ?MessageLevelStatusCode
    {
        return $this->code;
    }

    /** Returns the response description, or null when the provider omitted it. */
    public function description(): ?string
    {
        return $this->description;
    }

    /**
     * Returns the reasons in the order the provider reported them.
     *
     * @return list<ProviderMessageLevelStatusReason>
     */
    public function reasons(): array
    {
        return $this->reasons;
    }
}
