<?php

namespace DreaM1\EfakturaConnector\Domain\Ubl\Draft;

use DreaM1\EfakturaConnector\Domain\Ubl\CodeList\TaxSchemeCode;

/** cac:PartyTaxScheme: a tax registration (cbc:CompanyID) in a tax scheme, see CodeList\TaxSchemeCode. */
final class UblDraftPartyTaxScheme
{
    /** Party tax registration number written to cac:PartyTaxScheme/cbc:CompanyID. */
    private UblDraftIdentifier $companyId;
    /** Tax registration type written to cac:PartyTaxScheme/cac:TaxScheme/cbc:ID. */
    private TaxSchemeCode $taxScheme;

    /**
     * @param UblDraftIdentifier $companyId Company identifier and optional scheme supplied by the provider or host.
     * @param TaxSchemeCode      $taxScheme Tax scheme associated with the tax category.
     */
    public function __construct(UblDraftIdentifier $companyId, TaxSchemeCode $taxScheme)
    {
        $this->companyId = $companyId;
        $this->taxScheme = $taxScheme;
    }

    /**
     * Returns the company ID.
     *
     * @return UblDraftIdentifier
     */
    public function companyId(): UblDraftIdentifier
    {
        return $this->companyId;
    }

    /**
     * Returns the tax scheme.
     *
     * @return TaxSchemeCode
     */
    public function taxScheme(): TaxSchemeCode
    {
        return $this->taxScheme;
    }
}
