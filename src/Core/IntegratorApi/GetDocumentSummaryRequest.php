<?php

namespace DreaM1\EfakturaConnector\Core\IntegratorApi;

use DreaM1\EfakturaConnector\Support\Exceptions\InvalidInputException;

/** Billing document summary query for one calendar month (yyyyMM). */
final class GetDocumentSummaryRequest
{
    private string $month;

    /**
     * Creates a monthly summary request for a calendar month in yyyyMM format.
     *
     * @param  string                $month Billing month in yyyyMM format.
     * @throws InvalidInputException when the month does not use the yyyyMM format or is invalid.
     */
    public function __construct(string $month)
    {
        if (preg_match('/\A[0-9]{4}(0[1-9]|1[0-2])\z/', $month) !== 1) {
            throw new InvalidInputException('Billing month must use the yyyyMM format.');
        }

        $this->month = $month;
    }

    /** Returns the requested billing month in yyyyMM format. */
    public function month(): string
    {
        return $this->month;
    }
}
