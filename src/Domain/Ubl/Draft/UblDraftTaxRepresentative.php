<?php

namespace DreaM1\EfakturaConnector\Domain\Ubl\Draft;

/** cac:TaxRepresentativeParty data allowed by Peppol Billing. */
final class UblDraftTaxRepresentative
{
    /** Representative name written to cac:TaxRepresentativeParty/cac:PartyName/cbc:Name. */
    private string $name;
    /** Representative's postal address written to cac:TaxRepresentativeParty/cac:PostalAddress. */
    private UblDraftAddress $address;
    /** VAT registration written to cac:TaxRepresentativeParty/cac:PartyTaxScheme/cbc:CompanyID. */
    private UblDraftIdentifier $vatIdentifier;

    /**
     * Creates tax representative details for the document.
     *
     * @param string             $name          Registered name of the tax representative.
     * @param UblDraftAddress    $address       Postal address of the tax representative.
     * @param UblDraftIdentifier $vatIdentifier VAT identifier and optional scheme of the tax representative.
     */
    public function __construct(string $name, UblDraftAddress $address, UblDraftIdentifier $vatIdentifier)
    {
        $this->name          = $name;
        $this->address       = $address;
        $this->vatIdentifier = $vatIdentifier;
    }

    /** Returns the tax representative's registered name. */
    public function name(): string
    {
        return $this->name;
    }

    /** Returns the tax representative's postal address. */
    public function address(): UblDraftAddress
    {
        return $this->address;
    }

    /** Returns the tax representative's VAT identifier. */
    public function vatIdentifier(): UblDraftIdentifier
    {
        return $this->vatIdentifier;
    }
}
