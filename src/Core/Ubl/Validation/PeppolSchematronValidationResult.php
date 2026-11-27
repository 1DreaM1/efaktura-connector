<?php

namespace DreaM1\EfakturaConnector\Core\Ubl\Validation;

use DreaM1\EfakturaConnector\Support\Exceptions\InvalidInputException;

/** Findings reported by local CEN and Peppol Schematron validation. */
final class PeppolSchematronValidationResult
{
    /** @var LimitedValidationIssues<PeppolValidationIssue> */
    private LimitedValidationIssues $findings;

    /**
     * Creates a result from retained and omitted Schematron findings.
     *
     * @param  LimitedValidationIssues<PeppolValidationIssue>|null $findings Retained and omitted Schematron findings.
     * @throws InvalidInputException                               when the findings contain a different issue type.
     */
    public function __construct(?LimitedValidationIssues $findings = null)
    {
        $findings = $findings ?? new LimitedValidationIssues([], 0, PeppolValidationIssue::class);

        if ($findings->issueType() !== PeppolValidationIssue::class) {
            throw new InvalidInputException('Schematron results require PeppolValidationIssue findings.');
        }

        $this->findings = $findings;
    }

    /**
     * Returns retained Schematron findings.
     *
     * @return PeppolValidationIssue[]
     */
    public function issues(): array
    {
        return $this->findings->issues();
    }

    /**
     * Returns retained and omitted findings as one value for downstream pipeline stages.
     *
     * @return LimitedValidationIssues<PeppolValidationIssue>
     */
    public function findings(): LimitedValidationIssues
    {
        return $this->findings;
    }

    /**
     * Returns the number of findings omitted by the configured limit.
     *
     * @return int
     */
    public function omittedCount(): int
    {
        return $this->findings->omittedCount();
    }

    /** Whether any retained finding has error severity. */
    public function hasErrors(): bool
    {
        foreach ($this->findings->issues() as $issue) {
            if ($issue->severity()->isError()) {
                return true;
            }
        }

        return false;
    }

    /** Whether the report has no errors and no omitted findings. */
    public function isValid(): bool
    {
        return ! $this->hasErrors() && $this->findings->omittedCount() === 0;
    }
}
