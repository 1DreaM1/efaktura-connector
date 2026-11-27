<?php

namespace DreaM1\EfakturaConnector\Core\IntegratorApi;

use DreaM1\EfakturaConnector\Core\Pagination\PageToken;
use DreaM1\EfakturaConnector\Support\Exceptions\InvalidInputException;
use DreaM1\EfakturaConnector\Support\TypedArray;

/** One page of integrator participants and its optional continuation token. */
final class IntegratorParticipantList
{
    /** @var IntegratorParticipant[] */
    private array $participants;
    private ?PageToken $nextPageToken;

    /**
     * Creates one page of integrator participants and its continuation token.
     *
     * @param  IntegratorParticipant[] $participants  Participant values in the provider page.
     * @param  PageToken|null          $nextPageToken Token for retrieving the next page, if present.
     * @throws InvalidInputException   when participants contains a value of the wrong type.
     */
    public function __construct(array $participants, ?PageToken $nextPageToken)
    {
        $this->participants = TypedArray::valuesOfType(
            $participants,
            IntegratorParticipant::class,
            'Integrator participant page items must be IntegratorParticipant values.'
        );
        $this->nextPageToken = $nextPageToken;
    }

    /** @return IntegratorParticipant[] */
    public function participants(): array
    {
        return $this->participants;
    }

    /** Returns the token for the next page, or null when this is the final page. */
    public function nextPageToken(): ?PageToken
    {
        return $this->nextPageToken;
    }
}
