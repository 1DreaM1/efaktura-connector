<?php

namespace DreaM1\EfakturaConnector\Domain\Ubl;

use DreaM1\EfakturaConnector\Domain\Ubl\Code\CountryCode;

/** cac:AccountingSupplierParty or cac:AccountingCustomerParty; every value is null when the document leaves it out. */
final class UblDocumentParty
{
    /** Serialized array key containing the party name. */
    private const FIELD_NAME = 'name';
    /** Serialized array key containing the party's registration identifier. */
    private const FIELD_REGISTRATION_ID = 'registrationId';
    /** Serialized array key containing the party's VAT identifier. */
    private const FIELD_VAT_ID = 'vatId';
    /** Serialized array key containing the party's other tax registration identifier. */
    private const FIELD_TAX_REGISTRATION_ID = 'taxRegistrationId';
    /** Serialized array key containing the street name. */
    private const FIELD_STREET = 'street';
    /** Serialized array key containing the building number. */
    private const FIELD_BUILDING_NUMBER = 'buildingNumber';
    /** Serialized array key containing the postal code. */
    private const FIELD_POSTAL_CODE = 'postalCode';
    /** Serialized array key containing the city name. */
    private const FIELD_CITY = 'city';
    /** Serialized array key containing the country code. */
    private const FIELD_COUNTRY_CODE = 'countryCode';
    /** Serialized array key containing the endpoint scheme. */
    private const FIELD_ENDPOINT_SCHEME = 'endpointScheme';
    /** Serialized array key containing the endpoint identifier value. */
    private const FIELD_ENDPOINT_VALUE = 'endpointValue';

    private ?string $name;
    private ?UblEndpointIdentifier $endpoint;
    private UblDocumentPartyIdentifiers $identifiers;
    private UblDocumentAddress $address;

    /** Restricts construction to factories that map parsed UBL party values. */
    private function __construct(
        ?string $name,
        ?UblEndpointIdentifier $endpoint,
        UblDocumentPartyIdentifiers $identifiers,
        UblDocumentAddress $address
    ) {
        $this->name        = $name;
        $this->endpoint    = $endpoint;
        $this->identifiers = $identifiers;
        $this->address     = $address;
    }

    /**
     * Creates a value from its parts.
     *
     * @param  string|null                 $name        Party name from the UBL document.
     * @param  UblEndpointIdentifier|null  $endpoint    UBL electronic address from cbc:EndpointID.
     * @param  UblDocumentPartyIdentifiers $identifiers Registration and tax identifiers of the party.
     * @param  UblDocumentAddress          $address     Postal address of the party.
     * @return self
     */
    public static function fromValues(
        ?string $name,
        ?UblEndpointIdentifier $endpoint,
        UblDocumentPartyIdentifiers $identifiers,
        UblDocumentAddress $address
    ): self {
        return new self($name, $endpoint, $identifiers, $address);
    }

    /**
     * Restores party details from their array representation.
     *
     * @param  array<string, mixed> $party the form produced by toArray()
     * @return self
     */
    public static function fromArray(array $party): self
    {
        return self::fromValues(
            UblArrayValue::optionalString($party, self::FIELD_NAME),
            UblArrayValue::endpointIdentifier($party, self::FIELD_ENDPOINT_SCHEME, self::FIELD_ENDPOINT_VALUE),
            new UblDocumentPartyIdentifiers(
                UblArrayValue::optionalString($party, self::FIELD_REGISTRATION_ID),
                UblArrayValue::optionalString($party, self::FIELD_VAT_ID),
                UblArrayValue::optionalString($party, self::FIELD_TAX_REGISTRATION_ID)
            ),
            new UblDocumentAddress(
                UblArrayValue::optionalString($party, self::FIELD_STREET),
                UblArrayValue::optionalString($party, self::FIELD_BUILDING_NUMBER),
                UblArrayValue::optionalString($party, self::FIELD_POSTAL_CODE),
                UblArrayValue::optionalString($party, self::FIELD_CITY),
                UblArrayValue::optionalString($party, self::FIELD_COUNTRY_CODE)
            )
        );
    }

    /**
     * cac:PartyLegalEntity/cbc:RegistrationName, else cac:PartyName/cbc:Name.
     *
     * @return string|null
     */
    public function name(): ?string
    {
        return $this->name;
    }

    /**
     * cbc:EndpointID as written, without any Peppol format check.
     *
     * @return UblEndpointIdentifier|null
     */
    public function endpoint(): ?UblEndpointIdentifier
    {
        return $this->endpoint;
    }

    /** Returns the registration and tax identifiers of the party. */
    public function identifiers(): UblDocumentPartyIdentifiers
    {
        return $this->identifiers;
    }

    /** Returns the postal address of the party. */
    public function address(): UblDocumentAddress
    {
        return $this->address;
    }

    /** Returns the registration identifier, or null when the document leaves it out. */
    public function registrationId(): ?string
    {
        return $this->identifiers->registrationId();
    }

    /** Returns the VAT identifier, or null when the document leaves it out. */
    public function vatId(): ?string
    {
        return $this->identifiers->vatId();
    }

    /** Returns the other tax registration identifier, or null when the document leaves it out. */
    public function taxRegistrationId(): ?string
    {
        return $this->identifiers->taxRegistrationId();
    }

    /** Returns the street. */
    public function street(): ?string
    {
        return $this->address->street();
    }

    /** Returns the building number. */
    public function buildingNumber(): ?string
    {
        return $this->address->buildingNumber();
    }

    /** Returns the postal code. */
    public function postalCode(): ?string
    {
        return $this->address->postalCode();
    }

    /** Returns the city. */
    public function city(): ?string
    {
        return $this->address->city();
    }

    /** Returns the country code as written in the document. */
    public function countryCode(): ?string
    {
        return $this->address->countryCode();
    }

    /** Returns the recognized canonical code while countryCode() preserves the original document text. */
    public function recognizedCountryCode(): ?CountryCode
    {
        return $this->address->recognizedCountryCode();
    }

    /**
     * Returns party values with an unambiguous endpoint scheme/value pair.
     *
     * @return array{name: string|null, registrationId: string|null, vatId: string|null, taxRegistrationId: string|null, street: string|null, buildingNumber: string|null, postalCode: string|null, city: string|null, countryCode: string|null, endpointScheme: string|null, endpointValue: string|null}
     */
    public function toArray(): array
    {
        $endpoint = $this->endpoint !== null
            ? $this->endpoint->toArray()
            : ['scheme' => null, 'value' => null];

        return [
            self::FIELD_NAME                => $this->name,
            self::FIELD_REGISTRATION_ID     => $this->identifiers->registrationId(),
            self::FIELD_VAT_ID              => $this->identifiers->vatId(),
            self::FIELD_TAX_REGISTRATION_ID => $this->identifiers->taxRegistrationId(),
            self::FIELD_STREET              => $this->address->street(),
            self::FIELD_BUILDING_NUMBER     => $this->address->buildingNumber(),
            self::FIELD_POSTAL_CODE         => $this->address->postalCode(),
            self::FIELD_CITY                => $this->address->city(),
            self::FIELD_COUNTRY_CODE        => $this->address->countryCode(),
            self::FIELD_ENDPOINT_SCHEME     => $endpoint['scheme'],
            self::FIELD_ENDPOINT_VALUE      => $endpoint['value'],
        ];
    }
}
