<?php

namespace DreaM1\EfakturaConnector\Core\CodeLists;

/** Stable identifiers for the bundled code lists exposed by CodeListRegistryInterface. */
final class CodeListIdentifiers
{
    /** Registry identifier for country codes. */
    public const COUNTRIES = 'countries';
    /** Registry identifier for currency codes. */
    public const CURRENCIES = 'currencies';
    /** Registry identifier for units of measure. */
    public const UNITS = 'units';
    /** Registry identifier for electronic address schemes. */
    public const ELECTRONIC_ADDRESS_SCHEMES = 'electronic_address_schemes';
    /** Registry identifier for participant identifier schemes. */
    public const PARTICIPANT_IDENTIFIER_SCHEMES = 'participant_identifier_schemes';
    /** Registry identifier for UBL document type codes. */
    public const UBL_DOCUMENT_TYPES = 'ubl_document_types';
    /** Registry identifier for additional document type codes. */
    public const ADDITIONAL_DOCUMENT_TYPES = 'additional_document_types';
    /** Registry identifier for tax scheme identifiers. */
    public const TAX_SCHEMES = 'tax_schemes';

    /** Registry identifier for Peppol Billing ICD values. */
    public const PEPPOL_BILLING_ICD = 'peppol_billing.icd';
    /** Registry identifier for Peppol Billing VAT category codes. */
    public const PEPPOL_BILLING_VAT_CATEGORY = 'peppol_billing.vat_category';
    /** Registry identifier for Peppol Billing item type codes. */
    public const PEPPOL_BILLING_ITEM_TYPE = 'peppol_billing.item_type';
    /** Registry identifier for Peppol Billing invoice type codes. */
    public const PEPPOL_BILLING_INVOICE_TYPE = 'peppol_billing.invoice_type';
    /** Registry identifier for Peppol Billing credit note type codes. */
    public const PEPPOL_BILLING_CREDIT_NOTE_TYPE = 'peppol_billing.credit_note_type';
    /** Registry identifier for Peppol Billing invoiced object scheme codes. */
    public const PEPPOL_BILLING_INVOICED_OBJECT_SCHEME = 'peppol_billing.invoiced_object_scheme';
    /** Registry identifier for Peppol Billing VAT date codes. */
    public const PEPPOL_BILLING_VAT_DATE_CODE = 'peppol_billing.vat_date_code';
    /** Registry identifier for Peppol Billing payment means codes. */
    public const PEPPOL_BILLING_PAYMENT_MEANS = 'peppol_billing.payment_means';
    /** Registry identifier for Peppol Billing allowance reason codes. */
    public const PEPPOL_BILLING_ALLOWANCE_REASON = 'peppol_billing.allowance_reason';
    /** Registry identifier for Peppol Billing charge reason codes. */
    public const PEPPOL_BILLING_CHARGE_REASON = 'peppol_billing.charge_reason';
    /** Registry identifier for Peppol Billing MIME codes. */
    public const PEPPOL_BILLING_MIME = 'peppol_billing.mime';
    /** Registry identifier for Peppol Billing SEPA payment codes. */
    public const PEPPOL_BILLING_SEPA = 'peppol_billing.sepa';
    /** Registry identifier for Peppol Billing VAT exemption codes. */
    public const PEPPOL_BILLING_VATEX = 'peppol_billing.vatex';

    private function __construct()
    {
    }

    /** Prefixes a PeppolBillingCodeList source identifier with its registry namespace. */
    public static function peppolBilling(string $sourceIdentifier): string
    {
        return 'peppol_billing.' . $sourceIdentifier;
    }
}
