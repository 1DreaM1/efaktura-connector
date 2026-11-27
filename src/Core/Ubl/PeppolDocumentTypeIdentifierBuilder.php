<?php

namespace DreaM1\EfakturaConnector\Core\Ubl;

use DreaM1\EfakturaConnector\Domain\EFaktura\PeppolDocumentTypeIdentifier;
use DreaM1\EfakturaConnector\Domain\Ubl\PeppolBillingProfile;
use DreaM1\EfakturaConnector\Domain\Ubl\UblDocumentRoot;

/** Builds Peppol document type identifiers for UBL Invoice and CreditNote documents. */
final class PeppolDocumentTypeIdentifierBuilder
{
    /**
     * Builds a typed Peppol document type identifier for an Invoice or CreditNote root.
     *
     * @param  string                       $kind            UBL root kind, such as `Invoice` or `CreditNote`.
     * @param  string|null                  $customizationId UBL customization identifier; defaults to Peppol Billing.
     * @return PeppolDocumentTypeIdentifier
     */
    public static function forDocument(string $kind, ?string $customizationId = null): PeppolDocumentTypeIdentifier
    {
        $kind = UblDocumentRoot::fromString($kind)->value();

        return PeppolDocumentTypeIdentifier::fromString(
            UblNamespaces::DOCUMENT_BASE . $kind . '-' . UblVersion::MAJOR_VERSION . '::' . $kind . '##'
            . ($customizationId ?? PeppolBillingProfile::CUSTOMIZATION_ID)
            . '::' . UblVersion::VERSION
        );
    }
}
