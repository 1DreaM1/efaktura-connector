<?php

namespace DreaM1\EfakturaConnector\Core\IntegratorApi;

use DreaM1\EfakturaConnector\Support\Timestamp;

/** Participant onboarding data submitted to the Integrator API for one PDS subject. */
final class PdsSubject
{
    private ?Timestamp $created;
    private ?string $dic;
    private ?string $legalName;
    private ?string $companyEmail;
    private ?string $companyPhone;
    private ?PdsVerificationToken $verificationToken;

    /** Stores optional values for a PDS subject request. */
    public function __construct(
        ?Timestamp $created,
        ?string $dic,
        ?string $legalName,
        ?string $companyEmail,
        ?string $companyPhone,
        ?PdsVerificationToken $verificationToken
    ) {
        $this->created           = $created;
        $this->dic               = $dic;
        $this->legalName         = $legalName;
        $this->companyEmail      = $companyEmail;
        $this->companyPhone      = $companyPhone;
        $this->verificationToken = $verificationToken;
    }

    /**
     * Redacts contact and registration data from var_dump() output.
     *
     * @return array{
     *     created: Timestamp|null,
     *     dic: string,
     *     legalName: string,
     *     companyEmail: string,
     *     companyPhone: string,
     *     verificationToken: string|null
     * }
     */
    public function __debugInfo(): array
    {
        return [
            'created'           => $this->created,
            'dic'               => '[redacted]',
            'legalName'         => '[redacted]',
            'companyEmail'      => '[redacted]',
            'companyPhone'      => '[redacted]',
            'verificationToken' => $this->verificationToken !== null ? '[redacted]' : null,
        ];
    }

    /** Returns the source creation time, when supplied. */
    public function created(): ?Timestamp
    {
        return $this->created;
    }

    /** Returns the Slovak tax identifier, when supplied. */
    public function dic(): ?string
    {
        return $this->dic;
    }

    /** Returns the participant legal name, when supplied. */
    public function legalName(): ?string
    {
        return $this->legalName;
    }

    /** Returns the company email address, when supplied. */
    public function companyEmail(): ?string
    {
        return $this->companyEmail;
    }

    /** Returns the company phone number, when supplied. */
    public function companyPhone(): ?string
    {
        return $this->companyPhone;
    }

    /** Returns the verification token, when supplied. */
    public function verificationToken(): ?PdsVerificationToken
    {
        return $this->verificationToken;
    }
}
