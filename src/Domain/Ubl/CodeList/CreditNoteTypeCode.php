<?php

namespace DreaM1\EfakturaConnector\Domain\Ubl\CodeList;

/** Peppol subset of UNCL 1001 credit note type codes for UBL CreditNote. */
final class CreditNoteTypeCode
{
    /** UN/CEFACT code '81' for a credit note related to goods or services. */
    public const CREDIT_NOTE_GOODS_OR_SERVICES = '81';
    /** UN/CEFACT code '83' for a credit note related to financial adjustments. */
    public const CREDIT_NOTE_FINANCIAL_ADJUSTMENTS = '83';
    /** UN/CEFACT code '381' for a credit note. */
    public const CREDIT_NOTE = DocumentTypeCode::CREDIT_NOTE;
    /** UN/CEFACT code '396' for a factored credit note. */
    public const FACTORED_CREDIT_NOTE = '396';
    /** UN/CEFACT code '532' for a forwarder's credit note. */
    public const FORWARDERS_CREDIT_NOTE = '532';
}
