<?php

namespace DreaM1\EfakturaConnector\Core\IntegratorApi;

use DreaM1\EfakturaConnector\Support\Exceptions\InvalidInputException;
use DreaM1\EfakturaConnector\Support\TypedArray;

/** Monthly billing document counts for participants under one integrator. */
final class IntegratorDocumentSummary
{
    private ?string $month;
    /** @var ParticipantDocumentSummary[]|null */
    private ?array $participants;

    /**
     * Creates a monthly summary for one integrator account.
     *
     * @param  string|null                       $month        Billing month in yyyyMM format, when supplied.
     * @param  ParticipantDocumentSummary[]|null $participants Per-participant counts, when supplied.
     * @throws InvalidInputException             when participants contains a value of the wrong type.
     */
    public function __construct(?string $month, ?array $participants)
    {
        $this->month        = $month;
        $this->participants = $participants !== null
            ? TypedArray::valuesOfType(
                $participants,
                ParticipantDocumentSummary::class,
                'Integrator document summary items must be ParticipantDocumentSummary values.'
            )
            : null;
    }

    /** Returns the summarized billing month in yyyyMM format, when supplied. */
    public function month(): ?string
    {
        return $this->month;
    }

    /** @return ParticipantDocumentSummary[]|null */
    public function participants(): ?array
    {
        return $this->participants;
    }
}
