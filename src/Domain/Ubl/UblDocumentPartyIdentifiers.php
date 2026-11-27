<?php

namespace DreaM1\EfakturaConnector\Domain\Ubl;

/** Registration and tax identifiers of a UBL party; each is null when the document leaves it out. */
final class UblDocumentPartyIdentifiers
{
    private ?string $registrationId;
    private ?string $vatId;
    private ?string $taxRegistrationId;

    /**
     * Creates the identifiers of one party.
     *
     * @param string|null $registrationId    Party's business registration identifier.
     * @param string|null $vatId             VAT registration identifier.
     * @param string|null $taxRegistrationId Other tax registration identifier.
     */
    public function __construct(
        ?string $registrationId = null,
        ?string $vatId = null,
        ?string $taxRegistrationId = null
    ) {
        $this->registrationId    = $registrationId;
        $this->vatId             = $vatId;
        $this->taxRegistrationId = $taxRegistrationId;
    }

    /** cac:PartyLegalEntity/cbc:CompanyID, else a cac:PartyIdentification other than the Slovak DIČ. */
    public function registrationId(): ?string
    {
        return $this->registrationId;
    }

    /** cac:PartyTaxScheme/cbc:CompanyID of the VAT scheme. */
    public function vatId(): ?string
    {
        return $this->vatId;
    }

    /** cac:PartyTaxScheme/cbc:CompanyID of another scheme, else the Slovak DIČ cac:PartyIdentification. */
    public function taxRegistrationId(): ?string
    {
        return $this->taxRegistrationId;
    }
}
