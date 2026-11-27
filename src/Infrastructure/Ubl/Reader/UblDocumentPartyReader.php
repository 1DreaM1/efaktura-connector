<?php

namespace DreaM1\EfakturaConnector\Infrastructure\Ubl\Reader;

use DreaM1\EfakturaConnector\Core\Ubl\UblAttributeName;
use DreaM1\EfakturaConnector\Core\Ubl\UblElementName;
use DreaM1\EfakturaConnector\Domain\Ubl\CodeList\PartyIdentifierScheme;
use DreaM1\EfakturaConnector\Domain\Ubl\CodeList\TaxSchemeCode;
use DreaM1\EfakturaConnector\Domain\Ubl\UblDocumentAddress;
use DreaM1\EfakturaConnector\Domain\Ubl\UblDocumentParty;
use DreaM1\EfakturaConnector\Domain\Ubl\UblDocumentPartyIdentifiers;
use DreaM1\EfakturaConnector\Infrastructure\Ubl\CodeList\CountryCodeList;

/** Reads supplier or customer details from a UBL Party element. @internal */
final class UblDocumentPartyReader
{
    use BuildsUblXPaths;

    /**
     * Reads party details from the supplied relative XPath path.
     *
     * A VAT PartyTaxScheme supplies the VAT ID. A non-VAT tax scheme supplies the tax registration
     * ID, with a Slovak DIC PartyIdentification as an optional fallback; its presence and format are
     * not validated here. Other party identifiers are used as the registration ID only when the legal
     * entity has no CompanyID.
     *
     * @param  UblXPath         $xpath XPath helper or expression used to query the XML document.
     * @param  string           $party Invoice party element to read.
     * @return UblDocumentParty
     */
    public function read(UblXPath $xpath, string $party): UblDocumentParty
    {
        return UblDocumentParty::fromValues(
            $this->readName($xpath, $party),
            $xpath->endpoint(self::cbcChild($party, UblElementName::ENDPOINT_ID)),
            $this->readIdentifiers($xpath, $party),
            $this->readAddress($xpath, $party)
        );
    }

    /** The registered name, else the trading name. */
    private function readName(UblXPath $xpath, string $party): ?string
    {
        return $xpath->firstValue(
            self::cbcChild(self::cacChild($party, UblElementName::PARTY_LEGAL_ENTITY), UblElementName::REGISTRATION_NAME),
            self::cbcChild(self::cacChild($party, UblElementName::PARTY_NAME), UblElementName::NAME)
        );
    }

    /** Reads the registration ID, the VAT ID and the other tax registration ID with their fallbacks. */
    private function readIdentifiers(UblXPath $xpath, string $party): UblDocumentPartyIdentifiers
    {
        $partyIdentification = self::cbcChild(
            self::cacChild($party, UblElementName::PARTY_IDENTIFICATION),
            UblElementName::ID
        );
        $dicScheme      = '@' . UblAttributeName::SCHEME_ID . '="' . PartyIdentifierScheme::SK_DIC . '"';
        $taxSchemeId    = self::cbcChild(self::cac(UblElementName::TAX_SCHEME), UblElementName::ID);
        $partyTaxScheme = self::cac(UblElementName::PARTY_TAX_SCHEME);
        $vatTaxScheme   = $partyTaxScheme . '[' . $taxSchemeId . '="' . TaxSchemeCode::VAT . '"]';
        $otherTaxScheme = $partyTaxScheme . '[' . $taxSchemeId . '!="' . TaxSchemeCode::VAT . '"]';

        return new UblDocumentPartyIdentifiers(
            // The legal entity CompanyID wins; another party identification is the fallback.
            $xpath->firstValue(
                self::cbcChild(self::cacChild($party, UblElementName::PARTY_LEGAL_ENTITY), UblElementName::COMPANY_ID),
                $partyIdentification . '[not(' . $dicScheme . ')]'
            ),
            $xpath->value(self::cbcChild(self::path($party, $vatTaxScheme), UblElementName::COMPANY_ID)),
            // A non-VAT tax scheme wins; the Slovak DIC party identification is the fallback.
            $xpath->firstValue(
                self::cbcChild(self::path($party, $otherTaxScheme), UblElementName::COMPANY_ID),
                $partyIdentification . '[' . $dicScheme . ']'
            )
        );
    }

    /** Reads the postal address and resolves its country code against the bundled list. */
    private function readAddress(UblXPath $xpath, string $party): UblDocumentAddress
    {
        $address     = self::cacChild($party, UblElementName::POSTAL_ADDRESS);
        $countryCode = $xpath->value(
            self::cbcChild(self::cacChild($address, UblElementName::COUNTRY), UblElementName::IDENTIFICATION_CODE)
        );

        return new UblDocumentAddress(
            $xpath->value(self::cbcChild($address, UblElementName::STREET_NAME)),
            $xpath->value(self::cbcChild($address, UblElementName::BUILDING_NUMBER)),
            $xpath->value(self::cbcChild($address, UblElementName::POSTAL_ZONE)),
            $xpath->value(self::cbcChild($address, UblElementName::CITY_NAME)),
            $countryCode,
            CountryCodeList::resolve($countryCode)
        );
    }
}
