<?php

namespace DreaM1\EfakturaConnector\Infrastructure\Ubl\Writer;

use DOMElement;
use DOMException;
use DreaM1\EfakturaConnector\Core\Ubl\UblElementName;
use DreaM1\EfakturaConnector\Domain\Ubl\CodeList\TaxSchemeCode;
use DreaM1\EfakturaConnector\Domain\Ubl\Draft\UblDocumentDraft;
use DreaM1\EfakturaConnector\Domain\Ubl\Draft\UblDraftAddress;
use DreaM1\EfakturaConnector\Domain\Ubl\Draft\UblDraftContact;
use DreaM1\EfakturaConnector\Domain\Ubl\Draft\UblDraftDelivery;
use DreaM1\EfakturaConnector\Domain\Ubl\Draft\UblDraftIdentifier;
use DreaM1\EfakturaConnector\Domain\Ubl\Draft\UblDraftParty;
use DreaM1\EfakturaConnector\Domain\Ubl\Draft\UblDraftPayee;
use DreaM1\EfakturaConnector\Domain\Ubl\Draft\UblDraftTaxRepresentative;

/** Writes party, delivery, payee, and tax representative aggregates. */
final class UblPartyWriter
{
    /**
     * Appends a supplier or customer party aggregate under its UBL role element.
     *
     * Children follow the UBL schema order: EndpointID, PartyIdentification, PartyName, PostalAddress,
     * PartyTaxScheme, PartyLegalEntity and Contact.
     *
     * @param  DOMElement    $root  UBL document root element.
     * @param  string        $role  Role element name, such as AccountingSupplierParty.
     * @param  UblDraftParty $draft Party values to serialize.
     * @throws DOMException  when a UBL element cannot be created
     */
    public static function appendParty(DOMElement $root, string $role, UblDraftParty $draft): void
    {
        // Build AccountingSupplierParty/Party or AccountingCustomerParty/Party, depending on the supplied role.
        $party = UblElementWriter::appendCac(
            UblElementWriter::appendCac($root, $role),
            UblElementName::PARTY
        );

        // Party/EndpointID carries its schemeID attribute.
        $endpoint = $draft->endpoint();
        UblElementWriter::appendIdentifier($party, UblElementName::ENDPOINT_ID, $endpoint->value(), $endpoint->scheme());

        foreach ($draft->identifications() as $identification) {
            $partyIdentification = UblElementWriter::appendCac($party, UblElementName::PARTY_IDENTIFICATION);
            UblElementWriter::appendDraftIdentifier($partyIdentification, UblElementName::ID, $identification);
        }

        if ($draft->tradingName() !== null) {
            // The trading name is PartyName/Name; the registered name is written later to PartyLegalEntity.
            self::appendPartyName($party, $draft->tradingName());
        }

        self::appendAddress($party, UblElementName::POSTAL_ADDRESS, $draft->address());

        foreach ($draft->taxSchemes() as $taxScheme) {
            self::appendPartyTaxScheme(
                $party,
                $taxScheme->companyId(),
                $taxScheme->taxScheme()->value()
            );
        }

        self::appendLegalEntity($party, $draft);

        if ($draft->contact() !== null) {
            self::appendContact($party, $draft->contact());
        }
    }

    /**
     * Appends the separate payee party aggregate.
     *
     * @param  DOMElement    $root  UBL document root element.
     * @param  UblDraftPayee $draft Payee values to serialize.
     * @throws DOMException  when a UBL element cannot be created
     */
    public static function appendPayee(DOMElement $root, UblDraftPayee $draft): void
    {
        // PayeeParty contains an optional PartyIdentification, PartyName, and optional PartyLegalEntity/CompanyID.
        $payee = UblElementWriter::appendCac($root, UblElementName::PAYEE_PARTY);

        if ($draft->identifier() !== null) {
            $identification = UblElementWriter::appendCac($payee, UblElementName::PARTY_IDENTIFICATION);
            UblElementWriter::appendDraftIdentifier($identification, UblElementName::ID, $draft->identifier());
        }

        self::appendPartyName($payee, $draft->name());

        if ($draft->legalCompanyId() !== null) {
            $legal = UblElementWriter::appendCac($payee, UblElementName::PARTY_LEGAL_ENTITY);
            UblElementWriter::appendDraftIdentifier($legal, UblElementName::COMPANY_ID, $draft->legalCompanyId());
        }
    }

    /**
     * Appends tax representative identity, address and VAT registration details.
     *
     * @param  DOMElement                $root  UBL document root element.
     * @param  UblDraftTaxRepresentative $draft Tax representative values to serialize.
     * @throws DOMException              when a UBL element cannot be created
     */
    public static function appendTaxRepresentative(DOMElement $root, UblDraftTaxRepresentative $draft): void
    {
        // TaxRepresentativeParty contains PartyName, PostalAddress, and a VAT PartyTaxScheme/CompanyID.
        $representative = UblElementWriter::appendCac($root, UblElementName::TAX_REPRESENTATIVE_PARTY);
        self::appendPartyName($representative, $draft->name());
        self::appendAddress($representative, UblElementName::POSTAL_ADDRESS, $draft->address());
        self::appendPartyTaxScheme($representative, $draft->vatIdentifier(), TaxSchemeCode::VAT);
    }

    /**
     * Appends available delivery date, location, address and party details.
     *
     * If no delivery fields are supplied, no cac:Delivery aggregate is written.
     *
     * @param  DOMElement       $root  UBL document root element.
     * @param  UblDocumentDraft $draft Draft containing document and delivery details.
     * @throws DOMException     when a UBL element cannot be created
     */
    public static function appendDelivery(DOMElement $root, UblDocumentDraft $draft): void
    {
        $delivery = $draft->delivery();

        // A delivery-level date takes precedence; retain the document-level date as a fallback.
        $actualDate = $delivery !== null && $delivery->actualDeliveryDate() !== null
            ? $delivery->actualDeliveryDate()
            : $draft->dates()->deliveryDate();

        if ($actualDate === null && ($delivery === null || ! self::hasDeliveryDetails($delivery))) {
            // Do not create an empty Delivery aggregate when all delivery fields are absent.
            return;
        }

        $element = UblElementWriter::appendCac($root, UblElementName::DELIVERY);
        // Delivery writes ActualDeliveryDate, optional DeliveryLocation/ID and Address, and optional DeliveryParty/PartyName.
        UblElementWriter::appendOptionalDate($element, UblElementName::ACTUAL_DELIVERY_DATE, $actualDate);

        if ($delivery === null) {
            return;
        }

        self::appendDeliveryLocation($element, $delivery);

        if ($delivery->deliveryPartyName() !== null) {
            $deliveryParty = UblElementWriter::appendCac($element, UblElementName::DELIVERY_PARTY);
            self::appendPartyName($deliveryParty, $delivery->deliveryPartyName());
        }
    }

    /** Whether a delivery carries a location identifier, an address or a delivery party name. */
    private static function hasDeliveryDetails(UblDraftDelivery $delivery): bool
    {
        return $delivery->locationIdentifier() !== null
            || $delivery->address() !== null
            || $delivery->deliveryPartyName() !== null;
    }

    /**
     * Appends DeliveryLocation with its optional identifier and address, unless the delivery has neither.
     *
     * @throws DOMException when a UBL element cannot be created
     */
    private static function appendDeliveryLocation(DOMElement $delivery, UblDraftDelivery $draft): void
    {
        $identifier = $draft->locationIdentifier();
        $address    = $draft->address();

        if ($identifier === null && $address === null) {
            return;
        }

        $location = UblElementWriter::appendCac($delivery, UblElementName::DELIVERY_LOCATION);

        if ($identifier !== null) {
            UblElementWriter::appendDraftIdentifier($location, UblElementName::ID, $identifier);
        }

        if ($address !== null) {
            self::appendAddress($location, UblElementName::ADDRESS, $address);
        }
    }

    /**
     * Appends PartyLegalEntity with the registered name and the optional company ID and legal form.
     *
     * @throws DOMException when a UBL element cannot be created
     */
    private static function appendLegalEntity(DOMElement $party, UblDraftParty $draft): void
    {
        $legal = UblElementWriter::appendCac($party, UblElementName::PARTY_LEGAL_ENTITY);
        UblElementWriter::appendCbc($legal, UblElementName::REGISTRATION_NAME, $draft->registrationName());

        if ($draft->legalCompanyId() !== null) {
            UblElementWriter::appendDraftIdentifier($legal, UblElementName::COMPANY_ID, $draft->legalCompanyId());
        }

        UblElementWriter::appendOptionalCbc($legal, UblElementName::COMPANY_LEGAL_FORM, $draft->companyLegalForm());
    }

    /**
     * Appends Contact with its optional Name, Telephone and ElectronicMail components.
     *
     * @throws DOMException when a UBL element cannot be created
     */
    private static function appendContact(DOMElement $party, UblDraftContact $draft): void
    {
        $contact = UblElementWriter::appendCac($party, UblElementName::CONTACT);
        UblElementWriter::appendOptionalCbc($contact, UblElementName::NAME, $draft->name());
        UblElementWriter::appendOptionalCbc($contact, UblElementName::TELEPHONE, $draft->telephone());

        $email = $draft->email();
        UblElementWriter::appendOptionalCbc(
            $contact,
            UblElementName::ELECTRONIC_MAIL,
            $email !== null ? $email->value() : null
        );
    }

    /**
     * Appends PartyTaxScheme with the company identifier and the tax scheme code.
     *
     * @throws DOMException when a UBL element cannot be created
     */
    private static function appendPartyTaxScheme(DOMElement $party, UblDraftIdentifier $companyId, string $schemeCode): void
    {
        $partyTaxScheme = UblElementWriter::appendCac($party, UblElementName::PARTY_TAX_SCHEME);
        UblElementWriter::appendDraftIdentifier($partyTaxScheme, UblElementName::COMPANY_ID, $companyId);

        $taxScheme = UblElementWriter::appendCac($partyTaxScheme, UblElementName::TAX_SCHEME);
        UblElementWriter::appendCbc($taxScheme, UblElementName::ID, $schemeCode);
    }

    /**
     * Appends PartyName/Name.
     *
     * @throws DOMException when a UBL element cannot be created
     */
    private static function appendPartyName(DOMElement $parent, string $name): void
    {
        $partyName = UblElementWriter::appendCac($parent, UblElementName::PARTY_NAME);
        UblElementWriter::appendCbc($partyName, UblElementName::NAME, $name);
    }

    /**
     * Appends a postal address and its country aggregate.
     *
     * @param  DOMElement      $parent      Party or delivery location receiving the address.
     * @param  string          $elementName UBL address element name.
     * @param  UblDraftAddress $draft       Address values to serialize.
     * @throws DOMException    when a UBL element cannot be created
     */
    private static function appendAddress(DOMElement $parent, string $elementName, UblDraftAddress $draft): void
    {
        // Address elements contain street, city, postal zone, country subdivision, address line, and country code fields.
        $address = UblElementWriter::appendCac($parent, $elementName);
        UblElementWriter::appendOptionalCbc($address, UblElementName::STREET_NAME, $draft->street());
        UblElementWriter::appendOptionalCbc($address, UblElementName::ADDITIONAL_STREET_NAME, $draft->additionalStreet());
        UblElementWriter::appendOptionalCbc($address, UblElementName::CITY_NAME, $draft->city());
        UblElementWriter::appendOptionalCbc($address, UblElementName::POSTAL_ZONE, $draft->postalZone());
        UblElementWriter::appendOptionalCbc($address, UblElementName::COUNTRY_SUBENTITY, $draft->countrySubentity());

        if ($draft->addressLine() !== null) {
            $line = UblElementWriter::appendCac($address, UblElementName::ADDRESS_LINE);
            UblElementWriter::appendCbc($line, UblElementName::LINE, $draft->addressLine());
        }

        $country = UblElementWriter::appendCac($address, UblElementName::COUNTRY);
        UblElementWriter::appendCbc(
            $country,
            UblElementName::IDENTIFICATION_CODE,
            $draft->countryCode()->value()
        );
    }
}
