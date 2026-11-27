<?php

namespace DreaM1\EfakturaConnector\Core\IntegratorApi;

use DreaM1\EfakturaConnector\Core\Documents\IdempotencyKey;
use DreaM1\EfakturaConnector\Support\Exceptions\InvalidInputException;
use DreaM1\EfakturaConnector\Support\TypedArray;

/** Input for creating an OAuth client with explicit participant permissions. */
final class CreateClientRequest
{
    private ?string $name;
    private ?string $contactEmail;
    /** @var IntegratorParticipantAccess[]|null */
    private ?array $participants;
    private IdempotencyKey $idempotencyKey;

    /**
     * @param  IdempotencyKey                     $idempotencyKey Stable UUID for safe request replay.
     * @param  string|null                        $name           Human-readable client name.
     * @param  string|null                        $contactEmail   Client contact email.
     * @param  IntegratorParticipantAccess[]|null $participants   Optional initial participant access list.
     * @throws InvalidInputException              when participants contains a value of the wrong type.
     */
    public function __construct(
        IdempotencyKey $idempotencyKey,
        ?string $name = null,
        ?string $contactEmail = null,
        ?array $participants = null
    ) {
        $this->name         = $name;
        $this->contactEmail = $contactEmail;
        $this->participants = $participants !== null
            ? TypedArray::valuesOfType(
                $participants,
                IntegratorParticipantAccess::class,
                'Integrator client participants must be IntegratorParticipantAccess values.'
            )
            : null;
        $this->idempotencyKey = $idempotencyKey;
    }

    /** Returns the key used to identify a repeated client creation request. */
    public function idempotencyKey(): IdempotencyKey
    {
        return $this->idempotencyKey;
    }

    /** Returns the optional human-readable name. */
    public function name(): ?string
    {
        return $this->name;
    }

    /** Returns the optional client contact email. */
    public function contactEmail(): ?string
    {
        return $this->contactEmail;
    }

    /**
     * Returns the optional initial participant permissions.
     *
     * @return IntegratorParticipantAccess[]|null
     */
    public function participants(): ?array
    {
        return $this->participants;
    }
}
