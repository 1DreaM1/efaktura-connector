<?php

namespace DreaM1\EfakturaConnector\Core\IntegratorApi;

use DreaM1\EfakturaConnector\Core\Configuration\OAuthClientId;
use DreaM1\EfakturaConnector\Support\Exceptions\InvalidInputException;
use DreaM1\EfakturaConnector\Support\Timestamp;
use DreaM1\EfakturaConnector\Support\TypedArray;

/** Typed OAuth client metadata returned by list, get and participant-access operations. */
final class IntegratorClient
{
    private ?OAuthClientId $clientId;
    private ?string $name;
    private ?IntegratorClientStatus $status;
    private ?Timestamp $createdAt;
    private ?Timestamp $approvedAt;
    /** @var IntegratorParticipantAccessResponse[]|null */
    private ?array $participants;

    /**
     * Creates a typed representation of provider OAuth client metadata.
     *
     * @param  OAuthClientId|null                         $clientId     Provider OAuth client identifier.
     * @param  string|null                                $name         Human-readable client name.
     * @param  IntegratorClientStatus|null                $status       Provider lifecycle status.
     * @param  Timestamp|null                             $createdAt    Time when the client was created.
     * @param  Timestamp|null                             $approvedAt   Time when the client was approved.
     * @param  IntegratorParticipantAccessResponse[]|null $participants Participant permissions, when included.
     * @throws InvalidInputException                      when participants contains a value of the wrong type.
     */
    public function __construct(
        ?OAuthClientId $clientId,
        ?string $name,
        ?IntegratorClientStatus $status,
        ?Timestamp $createdAt,
        ?Timestamp $approvedAt,
        ?array $participants
    ) {
        $this->clientId     = $clientId;
        $this->name         = $name;
        $this->status       = $status;
        $this->createdAt    = $createdAt;
        $this->approvedAt   = $approvedAt;
        $this->participants = $participants !== null
            ? TypedArray::valuesOfType(
                $participants,
                IntegratorParticipantAccessResponse::class,
                'Integrator client participants must be IntegratorParticipantAccessResponse values.'
            )
            : null;
    }

    /** Returns the provider OAuth client identifier, when available. */
    public function clientId(): ?OAuthClientId
    {
        return $this->clientId;
    }

    /** Returns the human-readable client name, when available. */
    public function name(): ?string
    {
        return $this->name;
    }

    /** Returns the provider lifecycle status, when available. */
    public function status(): ?IntegratorClientStatus
    {
        return $this->status;
    }

    /** Returns the client creation time, when supplied by the provider. */
    public function createdAt(): ?Timestamp
    {
        return $this->createdAt;
    }

    /** Returns the client approval time, when supplied by the provider. */
    public function approvedAt(): ?Timestamp
    {
        return $this->approvedAt;
    }

    /** @return IntegratorParticipantAccessResponse[]|null */
    public function participants(): ?array
    {
        return $this->participants;
    }
}
