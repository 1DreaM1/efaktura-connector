<?php

namespace DreaM1\EfakturaConnector\Domain\Ubl\CodeList;

use ReflectionClass;

/** UNCL 1001 document type constants commonly used by UBL Invoice and CreditNote documents. */
final class DocumentTypeCode
{
    /** UN/CEFACT document type code '71' for a request for payment. */
    public const REQUEST_FOR_PAYMENT = '71';
    /** UN/CEFACT document type code '80' for a debit note related to goods or services. */
    public const DEBIT_NOTE_GOODS_OR_SERVICES = '80';
    /** UN/CEFACT document type code '82' for a metered services invoice. */
    public const METERED_SERVICES_INVOICE = '82';
    /** UN/CEFACT document type code '84' for a debit note related to financial adjustments. */
    public const DEBIT_NOTE_FINANCIAL_ADJUSTMENTS = '84';
    /** UN/CEFACT document type code '102' for a tax notification. */
    public const TAX_NOTIFICATION = '102';
    /** UN/CEFACT document type code '218' for a final payment request after work completion. */
    public const FINAL_PAYMENT_REQUEST = '218';
    /** UN/CEFACT document type code '219' for payment of completed units. */
    public const PAYMENT_REQUEST_COMPLETED_UNITS = '219';
    /** UN/CEFACT document type code '326' for a partial invoice. */
    public const PARTIAL_INVOICE = '326';
    /** UN/CEFACT document type code '331' for an invoice including a packing list. */
    public const INVOICE_WITH_PACKING_LIST = '331';
    /** UN/CEFACT document type code '380' for a commercial invoice. */
    public const COMMERCIAL_INVOICE = '380';
    /** UN/CEFACT document type code '382' for a commission note. */
    public const COMMISSION_NOTE = '382';
    /** UN/CEFACT document type code '388' for a tax invoice. */
    public const TAX_INVOICE = '388';
    /** UN/CEFACT document type code '389' for a self-billed invoice. */
    public const SELF_BILLED_INVOICE = '389';
    /** UN/CEFACT document type code '393' for a factored invoice. */
    public const FACTORED_INVOICE = '393';
    /** UN/CEFACT document type code '395' for a consignment invoice. */
    public const CONSIGNMENT_INVOICE = '395';
    /** UN/CEFACT document type code '553' for a forwarder's invoice discrepancy report. */
    public const FORWARDERS_INVOICE_DISCREPANCY_REPORT = '553';
    /** UN/CEFACT document type code '575' for an insurer's invoice. */
    public const INSURERS_INVOICE = '575';
    /** UN/CEFACT document type code '623' for a forwarder's invoice. */
    public const FORWARDERS_INVOICE = '623';
    /** UN/CEFACT document type code '780' for a freight invoice. */
    public const FREIGHT_INVOICE = '780';
    /** UN/CEFACT document type code '817' for a claim notification. */
    public const CLAIM_NOTIFICATION = '817';
    /** UN/CEFACT document type code '870' for a consular invoice. */
    public const CONSULAR_INVOICE = '870';
    /** UN/CEFACT document type code '875' for a partial construction invoice. */
    public const PARTIAL_CONSTRUCTION_INVOICE = '875';
    /** UN/CEFACT document type code '876' for a partial final construction invoice. */
    public const PARTIAL_FINAL_CONSTRUCTION_INVOICE = '876';
    /** UN/CEFACT document type code '877' for a final construction invoice. */
    public const FINAL_CONSTRUCTION_INVOICE = '877';
    /** UN/CEFACT document type code '381' for a credit note. */
    public const CREDIT_NOTE = '381';
    /** UN/CEFACT document type code '383' for a debit note. */
    public const DEBIT_NOTE = '383';
    /** UN/CEFACT document type code '384' for a corrected invoice. */
    public const CORRECTED_INVOICE = '384';
    /** UN/CEFACT document type code '386' for a prepayment invoice. */
    public const PREPAYMENT_INVOICE = '386';
    /** @var string[]|null */
    private static ?array $codes = null;

    /**
     * Returns the values of the public document type code constants.
     *
     * @return string[]
     */
    public static function all(): array
    {
        if (self::$codes === null) {
            // All constants declared here are public; the unfiltered API is available on PHP 7.4.
            self::$codes = array_values(
                (new ReflectionClass(self::class))->getConstants()
            );
        }

        return self::$codes;
    }
}
