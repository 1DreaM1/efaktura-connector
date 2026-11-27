<?php

namespace DreaM1\EfakturaConnector\Infrastructure\EFaktura\Validation\Schema;

use DreaM1\EfakturaConnector\Core\Ubl\Validation\LimitedValidationIssues;
use DreaM1\EfakturaConnector\Core\Ubl\Validation\UblSchemaValidationIssue;

/** Internal result of an XSD validation pass, including retained libxml issues. */
final class XmlSchemaValidationResult
{
    private bool $valid;
    /** @var LimitedValidationIssues<UblSchemaValidationIssue> */
    private LimitedValidationIssues $issues;

    /**
     * Creates the XSD result with its validity flag and reported issues.
     * @param bool                                              $valid  Whether the validator considers the value valid.
     * @param LimitedValidationIssues<UblSchemaValidationIssue> $issues Retained findings and the number omitted.
     */
    public function __construct(bool $valid, LimitedValidationIssues $issues)
    {
        $this->valid  = $valid;
        $this->issues = $issues;
    }

    /**
     * Whether the value is valid.
     *
     * @return bool
     */
    public function isValid(): bool
    {
        return $this->valid;
    }

    /**
     * Returns the validation issues retained from libxml diagnostics.
     * @return UblSchemaValidationIssue[]
     */
    public function issues(): array
    {
        return $this->issues->issues();
    }

    /** Returns the number of findings omitted by the configured issue limit. */
    public function omittedCount(): int
    {
        return $this->issues->omittedCount();
    }
}
