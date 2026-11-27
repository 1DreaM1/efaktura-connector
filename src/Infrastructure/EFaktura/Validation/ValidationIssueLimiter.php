<?php

namespace DreaM1\EfakturaConnector\Infrastructure\EFaktura\Validation;

use DreaM1\EfakturaConnector\Core\Configuration\ConnectorRuntimeConfiguration;
use DreaM1\EfakturaConnector\Core\Ubl\Validation\LimitedValidationIssues;
use DreaM1\EfakturaConnector\Core\Ubl\Validation\UblValidationIssueInterface;
use DreaM1\EfakturaConnector\Support\Exceptions\InvalidInputException;

/**
 * Retains bounded validation issues and counts findings omitted after the configured limit.
 *
 * @template T of UblValidationIssueInterface
 */
final class ValidationIssueLimiter
{
    /** Default maximum number of validation findings retained in a result. */
    public const DEFAULT_LIMIT = ConnectorRuntimeConfiguration::DEFAULT_VALIDATION_MAX_ISSUES;

    private ?int $limit;
    /** @var list<T> */
    private array $issues     = [];
    private int $omittedCount = 0;
    /** @var class-string<T> */
    private string $issueType;

    /**
     * A null limit retains all findings for APIs that explicitly request an unbounded report.
     *
     * @param  int|null              $limit     Maximum number of findings to retain, or null for all of them.
     * @param  class-string<T>       $issueType Class every retained finding must be an instance of.
     * @throws InvalidInputException when the limit is invalid or the issue type does not implement the contract.
     */
    public function __construct(
        ?int $limit,
        string $issueType
    ) {
        if ($limit !== null) {
            self::assertLimit($limit);
        }

        if (! is_a($issueType, UblValidationIssueInterface::class, true)) {
            throw new InvalidInputException('Limited issue type must implement UblValidationIssueInterface.');
        }

        $this->limit     = $limit;
        $this->issueType = $issueType;
    }

    /**
     * Ensures a configured issue limit is within the supported range.
     *
     * @param  int                   $limit Maximum number of findings to retain.
     * @throws InvalidInputException when the limit is outside the supported range
     */
    public static function assertLimit(int $limit): void
    {
        if ($limit < 1 || $limit > ConnectorRuntimeConfiguration::MAX_CONFIGURED_VALIDATION_ISSUES) {
            throw new InvalidInputException('The validation issue limit is outside the supported range.');
        }
    }

    /**
     * Adds an issue that the caller has already constructed.
     *
     * @param  T                     $issue Finding to retain, or to count when the limit is reached.
     * @throws InvalidInputException when the issue does not match the configured issue type.
     */
    public function add(UblValidationIssueInterface $issue): void
    {
        if (! $issue instanceof $this->issueType) {
            throw new InvalidInputException('Validation issue does not match the configured issue type.');
        }

        if ($this->isFull()) {
            $this->omittedCount++;

            return;
        }

        $this->issues[] = $issue;
    }

    /**
     * Builds an issue only while capacity remains, avoiding allocations for omitted findings.
     *
     * @param  callable(): T         $issueFactory Builds the finding when capacity remains.
     * @throws InvalidInputException when the factory returns an issue of the wrong type.
     */
    public function addLazy(callable $issueFactory): void
    {
        if ($this->isFull()) {
            $this->omittedCount++;

            return;
        }

        $issue = $issueFactory();

        if (! $issue instanceof $this->issueType) {
            throw new InvalidInputException('Validation issue does not match the configured issue type.');
        }

        $this->issues[] = $issue;
    }

    /**
     * Records findings already omitted by an upstream bounded collector.
     *
     * @throws InvalidInputException when the count is negative.
     */
    public function addOmitted(int $count): void
    {
        if ($count < 0) {
            throw new InvalidInputException('Omitted validation issue count must not be negative.');
        }

        $this->omittedCount += $count;
    }

    /** @return LimitedValidationIssues<T> */
    public function result(): LimitedValidationIssues
    {
        return new LimitedValidationIssues($this->issues, $this->omittedCount, $this->issueType);
    }

    /**
     * Retains the configured number of validation issues and counts the rest.
     *
     * @param  UblValidationIssueInterface[]                        $issues Validation issues to include in the result.
     * @param  int                                                  $limit  Maximum number of findings to retain.
     * @return LimitedValidationIssues<UblValidationIssueInterface> Retained findings and the number omitted.
     * @throws InvalidInputException                                when the limit is outside the supported range
     */
    public static function limit(array $issues, int $limit = self::DEFAULT_LIMIT): LimitedValidationIssues
    {
        $collector = new self($limit, UblValidationIssueInterface::class);

        foreach ($issues as $issue) {
            $collector->add($issue);
        }

        return $collector->result();
    }

    private function isFull(): bool
    {
        return $this->limit !== null && count($this->issues) >= $this->limit;
    }
}
