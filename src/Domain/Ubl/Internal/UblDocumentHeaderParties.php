<?php

namespace DreaM1\EfakturaConnector\Domain\Ubl\Internal;

use DreaM1\EfakturaConnector\Domain\Ubl\UblEndpointIdentifier;

/** @internal Supplier and customer endpoints read from a UBL header. */
final class UblDocumentHeaderParties
{
    private ?UblEndpointIdentifier $supplierEndpoint;
    private ?UblEndpointIdentifier $customerEndpoint;

    public function __construct(
        ?UblEndpointIdentifier $supplierEndpoint,
        ?UblEndpointIdentifier $customerEndpoint
    ) {
        $this->supplierEndpoint = $supplierEndpoint;
        $this->customerEndpoint = $customerEndpoint;
    }

    public function supplierEndpoint(): ?UblEndpointIdentifier
    {
        return $this->supplierEndpoint;
    }

    public function customerEndpoint(): ?UblEndpointIdentifier
    {
        return $this->customerEndpoint;
    }
}
