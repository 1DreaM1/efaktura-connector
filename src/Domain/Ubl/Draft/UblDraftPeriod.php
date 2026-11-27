<?php

namespace DreaM1\EfakturaConnector\Domain\Ubl\Draft;

use DreaM1\EfakturaConnector\Domain\Ubl\CodeList\PeriodDescriptionCode;
use DreaM1\EfakturaConnector\Domain\Ubl\LocalDate;

/** cac:InvoicePeriod, including InvoiceLine and CreditNoteLine periods. */
final class UblDraftPeriod
{
    /** Optional period start date written to cbc:StartDate. */
    private ?LocalDate $startDate;
    /** Optional period end date written to cbc:EndDate. */
    private ?LocalDate $endDate;
    /** Optional coded period purpose written to cbc:DescriptionCode. */
    private ?PeriodDescriptionCode $descriptionCode;

    /**
     * Creates a document or line period with optional bounds and a period description code.
     *
     * @param LocalDate|null             $startDate       Inclusive start date of the period.
     * @param LocalDate|null             $endDate         Inclusive end date of the period.
     * @param PeriodDescriptionCode|null $descriptionCode Coded description of the period, when supplied.
     */
    public function __construct(
        ?LocalDate $startDate = null,
        ?LocalDate $endDate = null,
        ?PeriodDescriptionCode $descriptionCode = null
    ) {
        $this->startDate       = $startDate;
        $this->endDate         = $endDate;
        $this->descriptionCode = $descriptionCode;
    }

    /** Returns the period start date, or null when it is omitted. */
    public function startDate(): ?LocalDate
    {
        return $this->startDate;
    }

    /** Returns the period end date, or null when it is omitted. */
    public function endDate(): ?LocalDate
    {
        return $this->endDate;
    }

    /** Returns the period description code, or null when it is omitted. */
    public function descriptionCode(): ?PeriodDescriptionCode
    {
        return $this->descriptionCode;
    }
}
