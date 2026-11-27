<?php

namespace DreaM1\EfakturaConnector\Domain\Ubl\Draft;

/** cac:PayeeParty, present when the payee differs from the seller. */
final class UblDraftPayee
{
    /** Payee name written to cac:PayeeParty/cac:PartyName/cbc:Name. */
    private string $name;
    /** Optional additional payee ID written under cac:PartyIdentification. */
    private ?UblDraftIdentifier $identifier;
    /** Optional legal company ID written under cac:PartyLegalEntity/cbc:CompanyID. */
    private ?UblDraftIdentifier $legalCompanyId;

    /**
     * Creates the payee party, typically when payment is due to someone other than the seller.
     *
     * @param string                  $name           Registered name of the payee.
     * @param UblDraftIdentifier|null $identifier     Additional payee identifier, when supplied.
     * @param UblDraftIdentifier|null $legalCompanyId Legal company identifier, when supplied.
     */
    public function __construct(
        string $name,
        ?UblDraftIdentifier $identifier = null,
        ?UblDraftIdentifier $legalCompanyId = null
    ) {
        $this->name           = $name;
        $this->identifier     = $identifier;
        $this->legalCompanyId = $legalCompanyId;
    }

    /** Returns the payee's registered name. */
    public function name(): string
    {
        return $this->name;
    }

    /** Returns the additional payee identifier, or null when it is omitted. */
    public function identifier(): ?UblDraftIdentifier
    {
        return $this->identifier;
    }

    /** Returns the payee's legal company identifier, or null when it is omitted. */
    public function legalCompanyId(): ?UblDraftIdentifier
    {
        return $this->legalCompanyId;
    }
}
