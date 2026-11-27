<?php

namespace DreaM1\EfakturaConnector\Core\Ubl\Validation;

use DreaM1\EfakturaConnector\Support\Exceptions\InvalidInputException;
use DreaM1\EfakturaConnector\Support\TypedArray;

/**
 * Validation issues retained up to a configured limit, with an omitted-item count.
 *
 * @template-covariant T of UblValidationIssueInterface
 */
final class LimitedValidationIssues
{
    /** @var T[] */
    private array $issues;
    private int $omittedCount;
    /** @var class-string<UblValidationIssueInterface> */
    private string $issueType;

    /**
     * Creates a new LimitedValidationIssues value.
     * @param  T[]                                       $issues       Validation issues to include in the result.
     * @param  int                                       $omittedCount Number of findings omitted because of the configured result limit.
     * @param  class-string<UblValidationIssueInterface> $issueType    Runtime issue type retained by this value.
     * @throws InvalidInputException                     when the omitted count is negative.
     */
    public function __construct(
        array $issues,
        int $omittedCount,
        string $issueType = UblValidationIssueInterface::class
    ) {
        if (! is_a($issueType, UblValidationIssueInterface::class, true)) {
            throw new InvalidInputException('Limited issue type must implement UblValidationIssueInterface.');
        }

        $validatedIssues = TypedArray::valuesOfType(
            $issues,
            $issueType,
            'Limited issues must match their declared issue type.'
        );
        /** @var T[] $validatedIssues */
        $this->issues = $validatedIssues;

        if ($omittedCount < 0) {
            throw new InvalidInputException('Omitted validation issue count must not be negative.');
        }

        $this->omittedCount = $omittedCount;
        $this->issueType    = $issueType;
    }

    /**
     * Returns the issues.
     * @return T[]
     */
    public function issues(): array
    {
        return $this->issues;
    }

    /**
     * Returns the omitted count.
     *
     * @return int
     */
    public function omittedCount(): int
    {
        return $this->omittedCount;
    }

    /** Returns the runtime issue type enforced when this value was created. */
    public function issueType(): string
    {
        return $this->issueType;
    }
}
