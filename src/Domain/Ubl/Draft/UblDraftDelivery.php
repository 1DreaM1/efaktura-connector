<?php

namespace DreaM1\EfakturaConnector\Domain\Ubl\Draft;

use DreaM1\EfakturaConnector\Domain\Ubl\LocalDate;

/** cac:Delivery information in Peppol Billing. */
final class UblDraftDelivery
{
    /** Actual delivery date written to cac:Delivery/cbc:ActualDeliveryDate. */
    private ?LocalDate $actualDeliveryDate;
    /** Location identifier written to cac:DeliveryLocation/cbc:ID. */
    private ?UblDraftIdentifier $locationIdentifier;
    /** Delivery location address written to cac:DeliveryLocation/cac:Address. */
    private ?UblDraftAddress $address;
    /** Name of the receiving party, written to cac:DeliveryParty/cac:PartyName/cbc:Name. */
    private ?string $deliveryPartyName;

    /**
     * Creates optional UBL delivery information.
     *
     * @param LocalDate|null          $actualDeliveryDate Date the goods or services were delivered.
     * @param UblDraftIdentifier|null $locationIdentifier Identifier of the delivery location.
     * @param UblDraftAddress|null    $address            Postal address of the delivery location.
     * @param string|null             $deliveryPartyName  Name of the party receiving the delivery.
     */
    public function __construct(
        ?LocalDate $actualDeliveryDate = null,
        ?UblDraftIdentifier $locationIdentifier = null,
        ?UblDraftAddress $address = null,
        ?string $deliveryPartyName = null
    ) {
        $this->actualDeliveryDate = $actualDeliveryDate;
        $this->locationIdentifier = $locationIdentifier;
        $this->address            = $address;
        $this->deliveryPartyName  = UblDraftText::emptyToNull($deliveryPartyName);
    }

    /** Returns the actual delivery date, or null when it is omitted. */
    public function actualDeliveryDate(): ?LocalDate
    {
        return $this->actualDeliveryDate;
    }

    /** Returns the delivery location identifier, or null when it is omitted. */
    public function locationIdentifier(): ?UblDraftIdentifier
    {
        return $this->locationIdentifier;
    }

    /** Returns the delivery address, or null when it is omitted. */
    public function address(): ?UblDraftAddress
    {
        return $this->address;
    }

    /** Returns the delivery party name, or null when it is omitted. */
    public function deliveryPartyName(): ?string
    {
        return $this->deliveryPartyName;
    }
}
