<?php

namespace DreaM1\EfakturaConnector\Domain\Ubl;

use DreaM1\EfakturaConnector\Domain\Ubl\Code\CountryCode;

/** cac:PostalAddress of a UBL party; each component is null when the document leaves it out. */
final class UblDocumentAddress
{
    private ?string $street;
    private ?string $buildingNumber;
    private ?string $postalCode;
    private ?string $city;
    private ?string $countryCode;
    private ?CountryCode $recognizedCountryCode;

    /**
     * Creates a postal address from its components.
     *
     * @param string|null      $street                Street component of the postal address.
     * @param string|null      $buildingNumber        Building number in the postal address.
     * @param string|null      $postalCode            Postal code component of the address.
     * @param string|null      $city                  City component of the postal address.
     * @param string|null      $countryCode           Raw country code from the postal address.
     * @param CountryCode|null $recognizedCountryCode Canonical code when recognized by the bundled list.
     */
    public function __construct(
        ?string $street = null,
        ?string $buildingNumber = null,
        ?string $postalCode = null,
        ?string $city = null,
        ?string $countryCode = null,
        ?CountryCode $recognizedCountryCode = null
    ) {
        $this->street                = $street;
        $this->buildingNumber        = $buildingNumber;
        $this->postalCode            = $postalCode;
        $this->city                  = $city;
        $this->countryCode           = $countryCode;
        $this->recognizedCountryCode = $recognizedCountryCode;
    }

    /** Returns the street. */
    public function street(): ?string
    {
        return $this->street;
    }

    /** Returns the building number. */
    public function buildingNumber(): ?string
    {
        return $this->buildingNumber;
    }

    /** Returns the postal code. */
    public function postalCode(): ?string
    {
        return $this->postalCode;
    }

    /** Returns the city. */
    public function city(): ?string
    {
        return $this->city;
    }

    /** Returns the country code as written in the document. */
    public function countryCode(): ?string
    {
        return $this->countryCode;
    }

    /** Returns the recognized canonical code while countryCode() preserves the original document text. */
    public function recognizedCountryCode(): ?CountryCode
    {
        return $this->recognizedCountryCode;
    }
}
