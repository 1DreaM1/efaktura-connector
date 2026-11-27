<?php

namespace DreaM1\EfakturaConnector\Infrastructure\Ubl\Validation;

use DreaM1\EfakturaConnector\Domain\EFaktura\PeppolCustomizationIdentifier;
use DreaM1\EfakturaConnector\Domain\EFaktura\PeppolProcessIdentifier;
use DreaM1\EfakturaConnector\Domain\Ubl\PeppolBillingProfile;

/** Shared Peppol Billing identifiers accepted by the bundled validation rules. */
final class PeppolBillingIdentifiers
{
    /** @var string[] Peppol Billing process identifiers accepted by the bundled rules. */
    private const APPROVED_PROCESS_IDS = [
        PeppolBillingProfile::PROCESS_ID,
        PeppolBillingProfile::PROCESS_ID_FRANCE_BILLING_REGULATED,
        PeppolBillingProfile::PROCESS_ID_FRANCE_BILLING_NON_REGULATED,
        PeppolBillingProfile::PROCESS_ID_BILLING_WITH_RESPONSE,
    ];

    /**
     * Whether the value begins with the accepted Peppol Billing customization identifier.
     *
     * @param  PeppolCustomizationIdentifier|null $customizationId Candidate customization identifier.
     * @return bool                               Whether the identifier has an accepted prefix and format.
     */
    public static function hasValidCustomizationId(?PeppolCustomizationIdentifier $customizationId): bool
    {
        if ($customizationId === null) {
            return false;
        }

        $customizationId = trim($customizationId->value());

        return strpos($customizationId, PeppolBillingProfile::CUSTOMIZATION_ID) === 0
            && strpos($customizationId, '::') === false;
    }

    /**
     * Whether the value is one of the Peppol Billing process identifiers supported by this package.
     *
     * @param  PeppolProcessIdentifier|null $processId Candidate process identifier.
     * @return bool                         Whether this package supports the identifier.
     */
    public static function isApprovedProcessId(?PeppolProcessIdentifier $processId): bool
    {
        return $processId !== null
            && in_array(trim($processId->value()), self::APPROVED_PROCESS_IDS, true);
    }
}
