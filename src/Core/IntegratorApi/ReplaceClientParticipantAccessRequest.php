<?php

namespace DreaM1\EfakturaConnector\Core\IntegratorApi;

use DreaM1\EfakturaConnector\Support\Exceptions\InvalidInputException;
use DreaM1\EfakturaConnector\Support\TypedArray;

/** Complete replacement for one OAuth client's participant permission list. */
final class ReplaceClientParticipantAccessRequest
{
    private IntegratorClientReference $client;
    /** @var IntegratorParticipantAccess[] */
    private array $participants;

    /**
     * Creates a complete replacement for the client's participant permissions.
     *
     * @param  IntegratorClientReference     $client       OAuth client whose permissions will be replaced.
     * @param  IntegratorParticipantAccess[] $participants Complete replacement access list.
     * @throws InvalidInputException         when participants contains a value of the wrong type.
     */
    public function __construct(IntegratorClientReference $client, array $participants)
    {
        $this->client       = $client;
        $this->participants = TypedArray::valuesOfType(
            $participants,
            IntegratorParticipantAccess::class,
            'Integrator client participants must be IntegratorParticipantAccess values.'
        );
    }

    /** Returns the OAuth client whose participant access is being replaced. */
    public function client(): IntegratorClientReference
    {
        return $this->client;
    }

    /**
     * Returns the complete replacement participant permissions.
     *
     * @return IntegratorParticipantAccess[]
     */
    public function participants(): array
    {
        return $this->participants;
    }
}
