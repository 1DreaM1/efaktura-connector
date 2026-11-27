<?php

namespace DreaM1\EfakturaConnector\Core\IntegratorApi;

use DreaM1\EfakturaConnector\Core\Configuration\OAuthClientId;
use DreaM1\EfakturaConnector\Core\Configuration\OAuthClientSecret;
use DreaM1\EfakturaConnector\Support\Exceptions\InvalidInputException;
use DreaM1\EfakturaConnector\Support\TypedArray;

/** One-time OAuth credentials and metadata returned when creating a client. */
final class IntegratorClientCreation
{
    private ?OAuthClientId $clientId;
    private ?OAuthClientSecret $clientSecret;
    private ?string $name;
    private ?IntegratorClientStatus $status;
    /** @var IntegratorParticipantAccessResponse[]|null */
    private ?array $participants;

    /**
     * Creates the provider result for an OAuth client creation request.
     *
     * @param  OAuthClientId|null                         $clientId     Provider OAuth client identifier.
     * @param  OAuthClientSecret|null                     $clientSecret One-time secret returned by the provider.
     * @param  string|null                                $name         Human-readable client name.
     * @param  IntegratorClientStatus|null                $status       Provider lifecycle status.
     * @param  IntegratorParticipantAccessResponse[]|null $participants Participant permissions, when included.
     * @throws InvalidInputException                      when participants contains a value of the wrong type.
     */
    public function __construct(
        ?OAuthClientId $clientId,
        ?OAuthClientSecret $clientSecret,
        ?string $name,
        ?IntegratorClientStatus $status,
        ?array $participants
    ) {
        $this->clientId     = $clientId;
        $this->clientSecret = $clientSecret;
        $this->name         = $name;
        $this->status       = $status;
        $this->participants = $participants !== null
            ? TypedArray::valuesOfType(
                $participants,
                IntegratorParticipantAccessResponse::class,
                'Integrator client participants must be IntegratorParticipantAccessResponse values.'
            )
            : null;
    }

    /**
     * Redacts the one-time secret from var_dump() output.
     *
     * @return array{
     *     clientId: OAuthClientId|null,
     *     clientSecret: string|null,
     *     name: string|null,
     *     status: IntegratorClientStatus|null,
     *     participants: IntegratorParticipantAccessResponse[]|null
     * }
     */
    public function __debugInfo(): array
    {
        return [
            'clientId'     => $this->clientId,
            'clientSecret' => $this->clientSecret !== null ? '[redacted]' : null,
            'name'         => $this->name,
            'status'       => $this->status,
            'participants' => $this->participants,
        ];
    }

    /** Returns the provider OAuth client identifier, when available. */
    public function clientId(): ?OAuthClientId
    {
        return $this->clientId;
    }

    /** Returns the one-time OAuth secret; persist it securely when supplied. */
    public function clientSecret(): ?OAuthClientSecret
    {
        return $this->clientSecret;
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

    /** @return IntegratorParticipantAccessResponse[]|null */
    public function participants(): ?array
    {
        return $this->participants;
    }
}
