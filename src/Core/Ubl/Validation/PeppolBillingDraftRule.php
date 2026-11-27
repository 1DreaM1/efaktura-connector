<?php

namespace DreaM1\EfakturaConnector\Core\Ubl\Validation;

/**
 * Stable package identifiers for Peppol Billing draft preflight findings.
 * Normative references for these identifiers are listed in docs/wiki/Validation.md.
 */
final class PeppolBillingDraftRule
{
    /** Uses the shared reference PEPPOL-EN16931-R004. */
    public const SPECIFICATION_IDENTIFIER = 'specification_identifier';
    /** Uses the shared references PEPPOL-EN16931-R001 and PEPPOL-EN16931-R007. */
    public const BUSINESS_PROCESS = 'business_process';
    /** Uses the shared reference EN 16931 BR-02 (BT-1). */
    public const DOCUMENT_NUMBER = 'document_number';
    /** BT-5/BR-05/BR-CL-04; tax currency BT-6/PEPPOL-EN16931-R005/BR-CL-05. */
    public const CURRENCY = 'currency';
    /** ISO 3166-1 alpha-2 seller country code (EN 16931 BR-CL-14/15). */
    public const SELLER_COUNTRY = 'seller_country';
    /** ISO 3166-1 alpha-2 buyer country code (EN 16931 BR-CL-14/15). */
    public const BUYER_COUNTRY = 'buyer_country';
    /** ISO 3166-1 alpha-2 delivery country code (EN 16931 BR-CL-14/15). */
    public const DELIVERY_COUNTRY = 'delivery_country';
    /** ISO 3166-1 alpha-2 tax representative country code (EN 16931 BR-CL-14/15). */
    public const TAX_REPRESENTATIVE_COUNTRY = 'tax_representative_country';
    /** ISO 3166-1 alpha-2 item origin country code (EN 16931 BR-CL-14/15). */
    public const ORIGIN_COUNTRY = 'origin_country';
    /** Uses the shared references BT-34, PEPPOL-EN16931-R020 and BR-CL-25. */
    public const SELLER_ENDPOINT = 'seller_endpoint';
    /** Uses the shared references BT-49, PEPPOL-EN16931-R010 and BR-CL-25. */
    public const BUYER_ENDPOINT = 'buyer_endpoint';
    /** Uses the shared reference BT-10/BT-13 and PEPPOL-EN16931-R003. */
    public const BUYER_REFERENCE = 'buyer_reference';
    /** Positive Invoice drafts require BT-9/BT-20 under BR-CO-25; CreditNote drafts are exempt. */
    public const PAYMENT_DUE_DATE_OR_TERMS = PeppolBillingRule::PAYMENT_DUE_DATE_OR_TERMS;
    /** At most one document note, except for the German exception (PEPPOL-EN16931-R002). */
    public const DOCUMENT_NOTE_COUNT = 'document_note_count';
    /** At least one invoice or credit note line is present (EN 16931 BR-16). */
    public const INVOICE_LINES = 'invoice_lines';
    /** Unit codes use UN/ECE Recommendation 20/21 (EN 16931 BR-CL-23). */
    public const UNIT_CODE = 'unit_code';
}
