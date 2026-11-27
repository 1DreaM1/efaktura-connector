<?php

namespace DreaM1\EfakturaConnector\Core\IntegratorApi;

/** Company details of an Integrator participant; each value is null when the provider omitted it. */
final class IntegratorParticipantCompany
{
    private ?string $dic;
    private ?string $legalName;
    private ?string $email;
    private ?string $phone;

    /**
     * Creates the company details of one participant.
     *
     * @param string|null $dic       Participant tax identifier.
     * @param string|null $legalName Participant legal name.
     * @param string|null $email     Participant contact email.
     * @param string|null $phone     Participant contact phone.
     */
    public function __construct(
        ?string $dic = null,
        ?string $legalName = null,
        ?string $email = null,
        ?string $phone = null
    ) {
        $this->dic       = $dic;
        $this->legalName = $legalName;
        $this->email     = $email;
        $this->phone     = $phone;
    }

    /** Returns the participant tax identifier, when available. */
    public function dic(): ?string
    {
        return $this->dic;
    }

    /** Returns the participant legal name, when available. */
    public function legalName(): ?string
    {
        return $this->legalName;
    }

    /** Returns the participant contact email, when available. */
    public function email(): ?string
    {
        return $this->email;
    }

    /** Returns the participant contact phone, when available. */
    public function phone(): ?string
    {
        return $this->phone;
    }
}
