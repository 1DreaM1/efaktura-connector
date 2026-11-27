<?php

namespace DreaM1\EfakturaConnector\Domain\Ubl;

/** Peppol Billing profile identifiers supported by UBL document helpers. */
final class PeppolBillingProfile
{
    /** Customization identifier for the Peppol EN 16931 billing profile. */
    public const CUSTOMIZATION_ID = 'urn:cen.eu:en16931:2017#compliant#urn:fdc:peppol.eu:2017:poacc:billing:3.0';

    /** Process identifier for the Peppol EN 16931 billing profile. */
    public const PROCESS_ID = 'urn:fdc:peppol.eu:2017:poacc:billing:01:1.0';

    /** Process identifier for regulated French billing. */
    public const PROCESS_ID_FRANCE_BILLING_REGULATED = 'urn:peppol:france:billing:regulated';

    /** Process identifier for non-regulated French billing. */
    public const PROCESS_ID_FRANCE_BILLING_NON_REGULATED = 'urn:peppol:france:billing:non-regulated';

    /** Process identifier for Peppol BIS Billing with response. */
    public const PROCESS_ID_BILLING_WITH_RESPONSE = 'urn:peppol:bis:billing_with_response';
}
