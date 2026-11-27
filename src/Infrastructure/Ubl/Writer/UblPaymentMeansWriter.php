<?php

namespace DreaM1\EfakturaConnector\Infrastructure\Ubl\Writer;

use DOMElement;
use DOMException;
use DreaM1\EfakturaConnector\Core\Ubl\UblAttributeName;
use DreaM1\EfakturaConnector\Core\Ubl\UblElementName;
use DreaM1\EfakturaConnector\Domain\Ubl\Draft\UblDraftPaymentMeans;
use DreaM1\EfakturaConnector\Domain\Ubl\LocalDate;

/** Writes PaymentMeans aggregates in UBL schema order. */
final class UblPaymentMeansWriter
{
    /**
     * Appends PaymentMeans children in UBL sequence: code, due date, payment ID,
     * card account, payee account, and mandate.
     *
     * @param  DOMElement           $root              UBL document root receiving the payment means.
     * @param  UblDraftPaymentMeans $draft             Payment details to serialize.
     * @param  LocalDate|null       $creditNoteDueDate Due date for a credit note, written within this payment means entry.
     * @throws DOMException         when a UBL element cannot be created
     */
    public static function appendPaymentMeans(
        DOMElement $root,
        UblDraftPaymentMeans $draft,
        ?LocalDate $creditNoteDueDate
    ): void {
        // Each cac:PaymentMeans contains PaymentMeansCode (with optional name attribute) and optional PaymentDueDate.
        $payment = UblElementWriter::appendCac($root, UblElementName::PAYMENT_MEANS);
        $code    = UblElementWriter::appendCbc(
            $payment,
            UblElementName::PAYMENT_MEANS_CODE,
            $draft->code()->value()
        );
        UblElementWriter::setAttributeIfPresent($code, UblAttributeName::NAME, $draft->name());
        UblElementWriter::appendOptionalDate($payment, UblElementName::PAYMENT_DUE_DATE, $creditNoteDueDate);

        if ($draft->paymentId() !== null) {
            // PaymentID is the remittance identifier and may carry schemeID.
            UblElementWriter::appendIdentifier(
                $payment,
                UblElementName::PAYMENT_ID,
                $draft->paymentId()->value(),
                $draft->paymentId()->scheme()
            );
        }

        self::appendPaymentCard($payment, $draft);
        self::appendPayeeFinancialAccount($payment, $draft);
        self::appendPaymentMandate($payment, $draft);
    }

    /** @throws DOMException */
    private static function appendPaymentCard(DOMElement $payment, UblDraftPaymentMeans $draft): void
    {
        $cardDraft = $draft->cardAccount();

        if ($cardDraft === null) {
            return;
        }

        // CardAccount contains the primary account number, network, and optional holder name.
        $card = UblElementWriter::appendCac($payment, UblElementName::CARD_ACCOUNT);
        UblElementWriter::appendIdentifier(
            $card,
            UblElementName::PRIMARY_ACCOUNT_NUMBER_ID,
            $cardDraft->primaryAccountNumberId()->value(),
            $cardDraft->primaryAccountNumberId()->scheme()
        );
        UblElementWriter::appendIdentifier(
            $card,
            UblElementName::NETWORK_ID,
            $cardDraft->networkId()->value(),
            $cardDraft->networkId()->scheme()
        );
        UblElementWriter::appendOptionalCbc($card, UblElementName::HOLDER_NAME, $cardDraft->holderName());
    }

    /** @throws DOMException */
    private static function appendPayeeFinancialAccount(DOMElement $payment, UblDraftPaymentMeans $draft): void
    {
        if ($draft->accountId() === null) {
            return;
        }

        // PayeeFinancialAccount contains the account ID/name and an optional FinancialInstitutionBranch/ID.
        $account = UblElementWriter::appendCac($payment, UblElementName::PAYEE_FINANCIAL_ACCOUNT);
        UblElementWriter::appendIdentifier(
            $account,
            UblElementName::ID,
            $draft->accountId()->value(),
            $draft->accountId()->scheme()
        );
        UblElementWriter::appendOptionalCbc($account, UblElementName::NAME, $draft->accountName());

        if ($draft->financialInstitutionBranchId() !== null) {
            $branch = UblElementWriter::appendCac($account, UblElementName::FINANCIAL_INSTITUTION_BRANCH);
            UblElementWriter::appendIdentifier(
                $branch,
                UblElementName::ID,
                $draft->financialInstitutionBranchId()->value(),
                $draft->financialInstitutionBranchId()->scheme()
            );
        }
    }

    /** @throws DOMException */
    private static function appendPaymentMandate(DOMElement $payment, UblDraftPaymentMeans $draft): void
    {
        $mandateDraft = $draft->paymentMandate();

        if ($mandateDraft === null) {
            return;
        }

        // PaymentMandate contains an optional mandate ID and PayerFinancialAccount/ID.
        $mandate = UblElementWriter::appendCac($payment, UblElementName::PAYMENT_MANDATE);

        if ($mandateDraft->id() !== null) {
            UblElementWriter::appendIdentifier(
                $mandate,
                UblElementName::ID,
                $mandateDraft->id()->value(),
                $mandateDraft->id()->scheme()
            );
        }

        if ($mandateDraft->payerAccountId() !== null) {
            $payerAccount = UblElementWriter::appendCac($mandate, UblElementName::PAYER_FINANCIAL_ACCOUNT);
            UblElementWriter::appendIdentifier(
                $payerAccount,
                UblElementName::ID,
                $mandateDraft->payerAccountId()->value(),
                $mandateDraft->payerAccountId()->scheme()
            );
        }
    }
}
