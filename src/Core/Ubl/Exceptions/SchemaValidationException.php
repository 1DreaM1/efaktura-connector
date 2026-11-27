<?php

namespace DreaM1\EfakturaConnector\Core\Ubl\Exceptions;

use DreaM1\EfakturaConnector\Core\Ubl\Validation\UblSchemaValidationIssue;

/**
 * The document did not pass the local UBL XSD validation.
 */
final class SchemaValidationException extends UblValidationException
{
    private string $version;
    /** @var UblSchemaValidationIssue[] */
    private array $issues;
    private int $omittedCount;

    /**
     * Creates the schema exception with its version, retained issues and omitted-item count.
     * @param UblSchemaValidationIssue[] $issues       Validation issues to include in the result.
     * @param string                     $version      Version identifier for the resource or ruleset.
     * @param int                        $omittedCount Number of findings omitted because of the configured result limit.
     */
    public function __construct(string $version, array $issues, int $omittedCount = 0)
    {
        parent::__construct('The document did not pass UBL ' . $version . ' XSD validation.');
        $this->version      = $version;
        $this->issues       = array_values($issues);
        $this->omittedCount = $omittedCount;
    }

    /**
     * Returns the version.
     *
     * @return string
     */
    public function version(): string
    {
        return $this->version;
    }

    /**
     * Returns the issues.
     * @return UblSchemaValidationIssue[]
     */
    public function issues(): array
    {
        return $this->issues;
    }

    /**
     * Number of further issues left out to keep the result small.
     *
     * @return int
     */
    public function omittedCount(): int
    {
        return $this->omittedCount;
    }
}
