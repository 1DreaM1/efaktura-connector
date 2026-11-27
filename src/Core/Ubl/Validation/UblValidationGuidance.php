<?php

namespace DreaM1\EfakturaConnector\Core\Ubl\Validation;

use DreaM1\EfakturaConnector\Support\Exceptions\InvalidInputException;

/** Optional reference and correction hint for a validation finding. */
final class UblValidationGuidance
{
    private ?string $referenceUrl;
    private ?string $recommendedAction;

    /**
     * Creates validation guidance with at least one useful field.
     *
     * @param  string|null           $referenceUrl      Documentation URL for the applicable rule.
     * @param  string|null           $recommendedAction Concise correction hint for the finding.
     * @throws InvalidInputException when both fields are null, the URL is invalid or the action is blank.
     */
    public function __construct(?string $referenceUrl = null, ?string $recommendedAction = null)
    {
        if ($referenceUrl === null && $recommendedAction === null) {
            throw new InvalidInputException('Validation guidance must contain a reference or a recommended action.');
        }

        if ($referenceUrl !== null && filter_var($referenceUrl, FILTER_VALIDATE_URL) === false) {
            throw new InvalidInputException('Validation guidance reference must be a valid URL.');
        }

        if ($recommendedAction !== null && trim($recommendedAction) === '') {
            throw new InvalidInputException('Validation guidance action must not be blank.');
        }

        $this->referenceUrl      = $referenceUrl;
        $this->recommendedAction = $recommendedAction;
    }

    /** Returns the documentation URL, when available. */
    public function referenceUrl(): ?string
    {
        return $this->referenceUrl;
    }

    /** Returns the correction hint, when available. */
    public function recommendedAction(): ?string
    {
        return $this->recommendedAction;
    }

    /** @return array{reference_url: string|null, recommended_action: string|null} */
    public function toArray(): array
    {
        return [
            'reference_url'      => $this->referenceUrl,
            'recommended_action' => $this->recommendedAction,
        ];
    }
}
