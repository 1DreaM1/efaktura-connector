<?php

namespace DreaM1\EfakturaConnector\Domain\Ubl\Draft;

use DreaM1\EfakturaConnector\Domain\Ubl\Code\CountryCode;

/** Postal address components used for a party, delivery location, or tax representative. */
final class UblDraftAddress
{
    /** Required country code written to cac:Country/cbc:IdentificationCode. */
    private CountryCode $countryCode;
    /** Primary street name written to cbc:StreetName. */
    private ?string $street = null;
    /** Additional street name written to cbc:AdditionalStreetName. */
    private ?string $additionalStreet = null;
    /** City or locality written to cbc:CityName. */
    private ?string $city = null;
    /** Postal code or postal zone written to cbc:PostalZone. */
    private ?string $postalZone = null;
    /** State, province, or other subdivision written to cbc:CountrySubentity. */
    private ?string $countrySubentity = null;
    /** Unstructured address line written to cac:AddressLine/cbc:Line. */
    private ?string $addressLine = null;

    /**
     * Creates an address with its required ISO 3166-1 alpha-2 country code.
     *
     * @param CountryCode $countryCode Country in which the address is located.
     */
    public function __construct(CountryCode $countryCode)
    {
        $this->countryCode = $countryCode;
    }

    /**
     * Returns a copy with the street and address-line components; blank text is treated as absent.
     *
     * @param  string|null $street           Primary street or route.
     * @param  string|null $additionalStreet Additional street information.
     * @param  string|null $addressLine      Unstructured address line.
     * @return self
     */
    public function withLines(?string $street, ?string $additionalStreet = null, ?string $addressLine = null): self
    {
        $address                   = clone $this;
        $address->street           = UblDraftText::emptyToNull($street);
        $address->additionalStreet = UblDraftText::emptyToNull($additionalStreet);
        $address->addressLine      = UblDraftText::emptyToNull($addressLine);

        return $address;
    }

    /**
     * Returns a copy with the city, postal zone and country subdivision; blank text is treated as absent.
     *
     * @param  string|null $city             City or locality.
     * @param  string|null $postalZone       Postal code or zone.
     * @param  string|null $countrySubentity State, province or other subdivision.
     * @return self
     */
    public function withLocality(?string $city, ?string $postalZone, ?string $countrySubentity = null): self
    {
        $address                   = clone $this;
        $address->city             = UblDraftText::emptyToNull($city);
        $address->postalZone       = UblDraftText::emptyToNull($postalZone);
        $address->countrySubentity = UblDraftText::emptyToNull($countrySubentity);

        return $address;
    }

    /** Returns the address country code. */
    public function countryCode(): CountryCode
    {
        return $this->countryCode;
    }

    /** Returns the street name, or null when it is omitted. */
    public function street(): ?string
    {
        return $this->street;
    }

    /** Returns the additional street line, or null when it is omitted. */
    public function additionalStreet(): ?string
    {
        return $this->additionalStreet;
    }

    /** Returns the city name, or null when it is omitted. */
    public function city(): ?string
    {
        return $this->city;
    }

    /** Returns the postal code, or null when it is omitted. */
    public function postalZone(): ?string
    {
        return $this->postalZone;
    }

    /** Returns the country subdivision, or null when it is omitted. */
    public function countrySubentity(): ?string
    {
        return $this->countrySubentity;
    }

    /** Returns the unstructured address line, or null when it is omitted. */
    public function addressLine(): ?string
    {
        return $this->addressLine;
    }
}
