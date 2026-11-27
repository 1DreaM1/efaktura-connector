<?php

namespace DreaM1\EfakturaConnector\Domain\Ubl\Draft;

use DreaM1\EfakturaConnector\Domain\Ubl\Code\CountryCode;
use DreaM1\EfakturaConnector\Domain\Ubl\UblEndpointIdentifier;

/**
 * cac:Party of the supplier or the customer. The writer writes exactly what is given here;
 * which identifiers and tax registrations a party shows is the caller's decision.
 */
final class UblDraftParty
{
    /** Electronic address written to cac:Party/cbc:EndpointID. */
    private UblEndpointIdentifier $endpoint;
    /** Registered business name written to cac:PartyLegalEntity/cbc:RegistrationName. */
    private string $registrationName;
    /** Postal location written to cac:Party/cac:PostalAddress. */
    private UblDraftAddress $address;
    /** Trading name written to cac:PartyName/cbc:Name. */
    private ?string $tradingName = null;
    /** @var UblDraftIdentifier[] Additional business identifiers written under cac:PartyIdentification. */
    private array $identifications = [];
    /** @var UblDraftPartyTaxScheme[] Tax registrations written as cac:PartyTaxScheme aggregates. */
    private array $taxSchemes = [];
    /** Official company identifier written to cac:PartyLegalEntity/cbc:CompanyID. */
    private ?UblDraftIdentifier $legalCompanyId = null;
    /** Registered legal form written to cac:PartyLegalEntity/cbc:CompanyLegalForm. */
    private ?string $companyLegalForm = null;
    /** Optional contact name, telephone, and email written under cac:Contact. */
    private ?UblDraftContact $contact = null;

    /**
     * Creates the supplier or customer party written into the UBL document.
     *
     * @param UblEndpointIdentifier $endpoint         Electronic address written to cbc:EndpointID.
     * @param string                $registrationName Party name as registered for business purposes.
     * @param CountryCode           $countryCode      Country code for the party's postal address.
     */
    public function __construct(
        UblEndpointIdentifier $endpoint,
        string $registrationName,
        CountryCode $countryCode
    ) {
        $this->endpoint         = $endpoint;
        $this->registrationName = $registrationName;
        $this->address          = new UblDraftAddress($countryCode);
    }

    /**
     * Returns a copy with the party's postal address.
     *
     * @param  UblDraftAddress $address Postal address to use for the party.
     * @return self
     */
    public function withAddress(UblDraftAddress $address): self
    {
        $party          = clone $this;
        $party->address = $address;

        return $party;
    }

    /**
     * Sets the party's optional trading name (cac:PartyName).
     *
     * @param  string|null $tradingName Trading name, or null/blank to omit it.
     * @return self
     */
    public function withTradingName(?string $tradingName): self
    {
        $party              = clone $this;
        $party->tradingName = UblDraftText::emptyToNull($tradingName);

        return $party;
    }

    /**
     * Sets optional additional legal information about the registered entity.
     *
     * @param  string|null $companyLegalForm Legal form stated for the registered entity.
     * @return self
     */
    public function withCompanyLegalForm(?string $companyLegalForm): self
    {
        $party                   = clone $this;
        $party->companyLegalForm = UblDraftText::emptyToNull($companyLegalForm);

        return $party;
    }

    /**
     * Sets an optional contact point for the party.
     *
     * @param  UblDraftContact|null $contact Contact to use, or null to omit it.
     * @return self
     */
    public function withContact(?UblDraftContact $contact): self
    {
        $party          = clone $this;
        $party->contact = $contact;

        return $party;
    }

    /**
     * Adds an additional business identification for the party, separate from its electronic endpoint.
     *
     * @param  UblDraftIdentifier $identifier Identifier and optional scheme used for cac:PartyIdentification.
     * @return self
     */
    public function withIdentification(UblDraftIdentifier $identifier): self
    {
        $party                    = clone $this;
        $party->identifications[] = $identifier;

        return $party;
    }

    /**
     * Adds a tax registration held by the party.
     *
     * @param  UblDraftPartyTaxScheme $taxScheme Tax registration identifier and its tax scheme, such as VAT.
     * @return self
     */
    public function withTaxScheme(UblDraftPartyTaxScheme $taxScheme): self
    {
        $party               = clone $this;
        $party->taxSchemes[] = $taxScheme;

        return $party;
    }

    /**
     * Sets the party's legal company identifier in cac:PartyLegalEntity/cbc:CompanyID.
     *
     * @param  UblDraftIdentifier|null $companyId Official company identifier and optional scheme; null omits it.
     * @return self
     */
    public function withLegalCompanyId(?UblDraftIdentifier $companyId): self
    {
        $party                 = clone $this;
        $party->legalCompanyId = $companyId;

        return $party;
    }

    /** Returns the party's electronic endpoint identifier. */
    public function endpoint(): UblEndpointIdentifier
    {
        return $this->endpoint;
    }

    /** Returns the party's registered name. */
    public function registrationName(): string
    {
        return $this->registrationName;
    }

    /** Returns the party's postal address. */
    public function address(): UblDraftAddress
    {
        return $this->address;
    }

    /** Returns the optional trading name. */
    public function tradingName(): ?string
    {
        return $this->tradingName;
    }

    /**
     * Returns additional party identifiers in the order supplied.
     *
     * @return UblDraftIdentifier[]
     */
    public function identifications(): array
    {
        return $this->identifications;
    }

    /**
     * Returns the party's tax registrations in the order supplied.
     *
     * @return UblDraftPartyTaxScheme[]
     */
    public function taxSchemes(): array
    {
        return $this->taxSchemes;
    }

    /** Returns the optional legal company identifier. */
    public function legalCompanyId(): ?UblDraftIdentifier
    {
        return $this->legalCompanyId;
    }

    /** Returns the optional legal company form. */
    public function companyLegalForm(): ?string
    {
        return $this->companyLegalForm;
    }

    /** Returns the optional contact point. */
    public function contact(): ?UblDraftContact
    {
        return $this->contact;
    }
}
