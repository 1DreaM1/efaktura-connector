<?php

namespace DreaM1\EfakturaConnector\Infrastructure\Ubl\Validation;

use DreaM1\EfakturaConnector\Core\Ubl\Contracts\PeppolBillingDraftRulesInterface;
use DreaM1\EfakturaConnector\Core\Ubl\Exceptions\PeppolBillingDraftRulesException;
use DreaM1\EfakturaConnector\Core\Ubl\Validation\PeppolBillingDraftRule;
use DreaM1\EfakturaConnector\Domain\Ubl\Draft\UblDocumentDraft;
use DreaM1\EfakturaConnector\Domain\Ubl\Draft\UblDraftLine;
use DreaM1\EfakturaConnector\Domain\Ubl\Draft\UblDraftParty;
use DreaM1\EfakturaConnector\Infrastructure\Ubl\CodeList\CountryCodeList;
use DreaM1\EfakturaConnector\Infrastructure\Ubl\CodeList\CurrencyCodeList;
use DreaM1\EfakturaConnector\Infrastructure\Ubl\CodeList\ElectronicAddressSchemeCodeList;
use DreaM1\EfakturaConnector\Infrastructure\Ubl\CodeList\UnitCodeList;

/** Checks draft metadata, identifiers, currencies, countries, endpoints, references, payment terms, notes, and lines. */
final class PeppolBillingDraftRules implements PeppolBillingDraftRulesInterface
{
    /** Maximum number of document-level notes accepted by the draft rules. */
    private const MAX_DOCUMENT_NOTES = 1;

    /**
     * Returns identifiers for all draft rules that are not satisfied.
     *
     * @param  UblDocumentDraft $draft Document draft to check before serialization.
     * @return string[]         PeppolBillingDraftRule identifiers in rule declaration order.
     */
    public function violations(UblDocumentDraft $draft): array
    {
        $identityRules = $this->identityRules($draft);
        $lines         = $draft->lines();
        $lineRuleFacts = $this->lineRuleFacts($lines);

        // The keyed booleans preserve rule declaration order when failed identifiers are collected below.
        /** @var array<string, bool> $satisfied Rule identifier => whether the draft satisfies that rule. */
        $satisfied = array_merge(
            $identityRules,
            $this->countryRules($draft, $lineRuleFacts['originCountryValid']),
            $this->endpointRules($draft),
            $this->referenceAndPaymentRules($draft),
            $this->lineRules($lines, $lineRuleFacts['unitCodesValid'])
        );

        return array_keys(array_filter($satisfied, static fn (bool $isSatisfied): bool => ! $isSatisfied));
    }
    /**
     * Ensures the draft satisfies the supported Peppol Billing rules.
     *
     * @param  UblDocumentDraft                 $draft Document draft to check before serialization.
     * @throws PeppolBillingDraftRulesException when one or more rules are violated
     */
    public function assertSatisfied(UblDocumentDraft $draft): void
    {
        $violations = $this->violations($draft);

        if ($violations !== []) {
            throw new PeppolBillingDraftRulesException($violations);
        }
    }

    /**
     * Rules for the specification, process, document number and currencies.
     *
     * @return array<string, bool> Rule identifier => whether the draft satisfies that rule.
     */
    private function identityRules(UblDocumentDraft $draft): array
    {
        $invoiceCurrency = $draft->currency()->value();
        $taxCurrency     = $draft->taxCurrencyCode() !== null
            ? $draft->taxCurrencyCode()->value()
            : null;

        return [
            // Require an accepted Peppol customization/profile identifier and a non-blank document number.
            PeppolBillingDraftRule::SPECIFICATION_IDENTIFIER => PeppolBillingIdentifiers::hasValidCustomizationId(
                $draft->customizationId()
            ),
            PeppolBillingDraftRule::BUSINESS_PROCESS => PeppolBillingIdentifiers::isApprovedProcessId(
                $draft->profileId()
            ),
            PeppolBillingDraftRule::DOCUMENT_NUMBER => ! $draft->number()->isBlank(),
            // A tax currency is optional; when supplied it must be canonical and differ from the invoice currency.
            PeppolBillingDraftRule::CURRENCY => CurrencyCodeList::isCanonical($invoiceCurrency)
                && (
                    $taxCurrency === null
                    || (
                        $taxCurrency !== $invoiceCurrency
                        && CurrencyCodeList::isCanonical($taxCurrency)
                    )
                ),
        ];
    }

    /**
     * Rules for the seller, buyer, delivery, tax representative and line-origin country codes.
     *
     * @return array<string, bool> Rule identifier => whether the draft satisfies that rule.
     */
    private function countryRules(UblDocumentDraft $draft, bool $originCountryValid): array
    {
        $delivery          = $draft->delivery();
        $deliveryAddress   = $delivery !== null ? $delivery->address() : null;
        $taxRepresentative = $draft->taxRepresentative();

        // Every country code must be a canonical ISO code.
        return [
            PeppolBillingDraftRule::SELLER_COUNTRY   => CountryCodeList::isCanonical($this->supplierCountry($draft)),
            PeppolBillingDraftRule::BUYER_COUNTRY    => CountryCodeList::isCanonical($this->customerCountry($draft)),
            PeppolBillingDraftRule::DELIVERY_COUNTRY => $deliveryAddress === null
                || CountryCodeList::isCanonical($deliveryAddress->countryCode()->value()),
            PeppolBillingDraftRule::TAX_REPRESENTATIVE_COUNTRY => $taxRepresentative === null
                || CountryCodeList::isCanonical($taxRepresentative->address()->countryCode()->value()),
            PeppolBillingDraftRule::ORIGIN_COUNTRY => $originCountryValid,
        ];
    }

    /**
     * Rules for the seller and buyer electronic endpoints, which need a supported EAS scheme and a valid value.
     *
     * @return array<string, bool> Rule identifier => whether the draft satisfies that rule.
     */
    private function endpointRules(UblDocumentDraft $draft): array
    {
        return [
            PeppolBillingDraftRule::SELLER_ENDPOINT => $this->hasElectronicEndpoint($draft->supplier()),
            PeppolBillingDraftRule::BUYER_ENDPOINT  => $this->hasElectronicEndpoint($draft->customer()),
        ];
    }

    /**
     * Rules for the buyer or order reference, the due date or payment terms, and the number of notes.
     *
     * @return array<string, bool> Rule identifier => whether the draft satisfies that rule.
     */
    private function referenceAndPaymentRules(UblDocumentDraft $draft): array
    {
        return [
            // Accept either BuyerReference or OrderReference as the required buyer/order reference.
            PeppolBillingDraftRule::BUYER_REFERENCE => $draft->references()->buyerReference() !== null
                || $draft->references()->orderReference() !== null,
            // Credit notes and non-positive payables are exempt from the shared BR-CO-25 requirement.
            PeppolBillingDraftRule::PAYMENT_DUE_DATE_OR_TERMS => ! PeppolBillingDueDateOrTermsRule::isViolated(
                ! $draft->kind()->isCreditNote(),
                $this->isPositivePayableAmount($draft),
                $draft->dates()->dueDate() !== null,
                $draft->payment()->termsNote() !== null
            ),
            // The one-note limit has an exception when both parties are in Germany.
            PeppolBillingDraftRule::DOCUMENT_NOTE_COUNT => count($draft->notes()) <= self::MAX_DOCUMENT_NOTES
                || (
                    $this->supplierCountry($draft) === CountryCodeList::GERMANY
                    && $this->customerCountry($draft) === CountryCodeList::GERMANY
                ),
        ];
    }

    /**
     * Rules for the presence of invoice lines and canonical unit codes on each line and explicit price-base unit.
     *
     * @param  UblDraftLine[]      $lines Draft lines to check.
     * @return array<string, bool> Rule identifier => whether the draft satisfies that rule.
     */
    private function lineRules(array $lines, bool $unitCodesValid): array
    {
        return [
            PeppolBillingDraftRule::INVOICE_LINES => $lines !== [],
            PeppolBillingDraftRule::UNIT_CODE     => $unitCodesValid,
        ];
    }

    private function supplierCountry(UblDocumentDraft $draft): string
    {
        return $draft->supplier()->address()->countryCode()->value();
    }

    private function customerCountry(UblDocumentDraft $draft): string
    {
        return $draft->customer()->address()->countryCode()->value();
    }

    /**
     * Checks line countries and units in one pass, stopping each check after its first violation.
     *
     * @param  UblDraftLine[]                                        $lines
     * @return array{originCountryValid: bool, unitCodesValid: bool}
     */
    private function lineRuleFacts(array $lines): array
    {
        $originCountryValid = true;
        $unitCodesValid     = true;

        foreach ($lines as $line) {
            if ($originCountryValid) {
                $countryCode = $line->originCountryCode();

                // Check the optional Item/OriginCountry/IdentificationCode against the country code list.
                if ($countryCode !== null && ! CountryCodeList::isCanonical($countryCode->value())) {
                    $originCountryValid = false;
                }
            }

            if ($unitCodesValid) {
                $unitCode             = $line->unitCode()->value();
                $priceBaseUnitCode    = $line->priceBaseUnitCode();
                $hasPriceBaseQuantity = $line->priceBaseQuantity() !== null;

                // If the price has a base quantity, its explicit unit must be canonical and match the line unit.
                if (
                    ! UnitCodeList::isCanonical($unitCode)
                    || (
                        $hasPriceBaseQuantity
                        && $priceBaseUnitCode !== null
                        && (
                            ! UnitCodeList::isCanonical($priceBaseUnitCode->value())
                            || $priceBaseUnitCode->value() !== $unitCode
                        )
                    )
                ) {
                    $unitCodesValid = false;
                }
            }

            if (! $originCountryValid && ! $unitCodesValid) {
                break;
            }
        }

        return [
            'originCountryValid' => $originCountryValid,
            'unitCodesValid'     => $unitCodesValid,
        ];
    }

    private function hasElectronicEndpoint(UblDraftParty $party): bool
    {
        $endpoint = $party->endpoint();

        // Validate both the endpoint value and its scheme against the supported electronic-address schemes.
        return ElectronicAddressSchemeCodeList::isValidEndpoint($endpoint->scheme(), $endpoint->value());
    }

    private function isPositivePayableAmount(UblDocumentDraft $draft): bool
    {
        // BR-CO-25 uses PayableAmount to decide whether an invoice requires a due date or payment terms.
        $amount = trim($draft->totals()->payableAmount()->value());

        return PeppolBillingAmount::isPositive($amount);
    }
}
