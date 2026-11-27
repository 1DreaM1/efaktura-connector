<?php

namespace DreaM1\EfakturaConnector\Tests\Unit\Infrastructure\Ubl;

use DreaM1\EfakturaConnector\Core\Ubl\Exceptions\PeppolBillingDraftRulesException;
use DreaM1\EfakturaConnector\Core\Ubl\Validation\PeppolBillingDraftRule;
use DreaM1\EfakturaConnector\Core\Ubl\Validation\PeppolBillingRule;
use DreaM1\EfakturaConnector\Domain\EFaktura\PeppolCustomizationIdentifier;
use DreaM1\EfakturaConnector\Domain\EFaktura\PeppolProcessIdentifier;
use DreaM1\EfakturaConnector\Domain\Ubl\Code\CountryCode;
use DreaM1\EfakturaConnector\Domain\Ubl\Code\CurrencyCode;
use DreaM1\EfakturaConnector\Domain\Ubl\Code\UnitCode;
use DreaM1\EfakturaConnector\Domain\Ubl\CodeList\TaxCategoryCode;
use DreaM1\EfakturaConnector\Domain\Ubl\CodeList\TaxSchemeCode;
use DreaM1\EfakturaConnector\Domain\Ubl\Draft\UblDecimal;
use DreaM1\EfakturaConnector\Domain\Ubl\Draft\UblDocumentDraft;
use DreaM1\EfakturaConnector\Domain\Ubl\Draft\UblDocumentNumber;
use DreaM1\EfakturaConnector\Domain\Ubl\Draft\UblDraftAddress;
use DreaM1\EfakturaConnector\Domain\Ubl\Draft\UblDraftDates;
use DreaM1\EfakturaConnector\Domain\Ubl\Draft\UblDraftDelivery;
use DreaM1\EfakturaConnector\Domain\Ubl\Draft\UblDraftIdentifier;
use DreaM1\EfakturaConnector\Domain\Ubl\Draft\UblDraftLine;
use DreaM1\EfakturaConnector\Domain\Ubl\Draft\UblDraftParty;
use DreaM1\EfakturaConnector\Domain\Ubl\Draft\UblDraftPartyTaxScheme;
use DreaM1\EfakturaConnector\Domain\Ubl\Draft\UblDraftPayment;
use DreaM1\EfakturaConnector\Domain\Ubl\Draft\UblDraftReferences;
use DreaM1\EfakturaConnector\Domain\Ubl\Draft\UblDraftTaxCategory;
use DreaM1\EfakturaConnector\Domain\Ubl\Draft\UblDraftTaxRepresentative;
use DreaM1\EfakturaConnector\Domain\Ubl\Draft\UblDraftTotals;
use DreaM1\EfakturaConnector\Domain\Ubl\LocalDate;
use DreaM1\EfakturaConnector\Domain\Ubl\PeppolBillingProfile;
use DreaM1\EfakturaConnector\Domain\Ubl\UblDocumentRoot;
use DreaM1\EfakturaConnector\Domain\Ubl\UblEndpointIdentifier;
use DreaM1\EfakturaConnector\Infrastructure\Ubl\Validation\PeppolBillingDraftRules;
use PHPUnit\Framework\TestCase;

/** Tests Peppol Billing rules applied to UBL document drafts. */
class PeppolBillingDraftRulesTest extends TestCase
{
    public function testAcceptsACompletePeppolDraft(): void
    {
        $rules = new PeppolBillingDraftRules();
        $draft = $this->draft();

        $this->assertSame([], $rules->violations($draft));
        $rules->assertSatisfied($draft);
    }

    public function testAcceptsCustomizationIdentifiersWithAValidPeppolPrefix(): void
    {
        $rules           = new PeppolBillingDraftRules();
        $customizationId = PeppolBillingProfile::CUSTOMIZATION_ID . '#urn:example:extension';
        $draft           = $this->draft()->withSpecification(
            PeppolCustomizationIdentifier::fromString($customizationId),
            PeppolProcessIdentifier::fromString(PeppolBillingProfile::PROCESS_ID)
        );
        $invalidDraft = $this->draft()->withSpecification(
            PeppolCustomizationIdentifier::fromString(PeppolBillingProfile::CUSTOMIZATION_ID . '::invalid'),
            PeppolProcessIdentifier::fromString(PeppolBillingProfile::PROCESS_ID)
        );

        $this->assertNotContains(
            PeppolBillingDraftRule::SPECIFICATION_IDENTIFIER,
            $rules->violations($draft)
        );
        $this->assertContains(
            PeppolBillingDraftRule::SPECIFICATION_IDENTIFIER,
            $rules->violations($invalidDraft)
        );
    }

    public function testAcceptsApprovedPeppolBillingProcesses(): void
    {
        $rules      = new PeppolBillingDraftRules();
        $processIds = [
            'urn:peppol:france:billing:regulated',
            'urn:peppol:france:billing:non-regulated',
            'urn:peppol:bis:billing_with_response',
        ];

        foreach ($processIds as $processId) {
            $draft = $this->draft()->withSpecification(
                PeppolCustomizationIdentifier::fromString(PeppolBillingProfile::CUSTOMIZATION_ID),
                PeppolProcessIdentifier::fromString($processId)
            );

            $this->assertNotContains(
                PeppolBillingDraftRule::BUSINESS_PROCESS,
                $rules->violations($draft)
            );
        }

        $invalidDraft = $this->draft()->withSpecification(
            PeppolCustomizationIdentifier::fromString(PeppolBillingProfile::CUSTOMIZATION_ID),
            PeppolProcessIdentifier::fromString('urn:example:unapproved-process')
        );

        $this->assertContains(
            PeppolBillingDraftRule::BUSINESS_PROCESS,
            $rules->violations($invalidDraft)
        );

        $normalizedProcessDraft = $this->draft()->withSpecification(
            PeppolCustomizationIdentifier::fromString(PeppolBillingProfile::CUSTOMIZATION_ID),
            PeppolProcessIdentifier::fromString(' ' . PeppolBillingProfile::PROCESS_ID . ' ')
        );
        $this->assertNotContains(
            PeppolBillingDraftRule::BUSINESS_PROCESS,
            $rules->violations($normalizedProcessDraft)
        );
    }

    public function testDoesNotRequireTaxIdentifiersForSlovakParties(): void
    {
        $draft = $this->draft(
            $this->partyWithoutTax('0088:seller-1', 'SK'),
            $this->partyWithoutTax('0088:buyer-1', 'SK')
        );

        $this->assertSame([], (new PeppolBillingDraftRules())->violations($draft));
    }

    public function testRejectsElectronicAddressSchemesOutsideTheEasCodeList(): void
    {
        $draft = $this->draft(
            $this->party('9999:seller-1', 'SK'),
            $this->party('0088:buyer-1', 'SK')
        );
        $buyerDraft = $this->draft(
            $this->party('0088:seller-1', 'SK'),
            $this->party('9999:buyer-1', 'SK')
        );

        $this->assertSame([
            PeppolBillingDraftRule::SELLER_ENDPOINT,
        ], (new PeppolBillingDraftRules())->violations($draft));
        $this->assertSame([
            PeppolBillingDraftRule::BUYER_ENDPOINT,
        ], (new PeppolBillingDraftRules())->violations($buyerDraft));
    }

    public function testRejectsSchemesAllowedByCenBrCl25ButRemovedFromPeppolEas(): void
    {
        $sellerDraft = $this->draft(
            $this->party('0037:seller-1', 'SK'),
            $this->party('0088:buyer-1', 'SK')
        );
        $buyerDraft = $this->draft(
            $this->party('0088:seller-1', 'SK'),
            $this->party('0037:buyer-1', 'SK')
        );

        $this->assertSame([
            PeppolBillingDraftRule::SELLER_ENDPOINT,
        ], (new PeppolBillingDraftRules())->violations($sellerDraft));
        $this->assertSame([
            PeppolBillingDraftRule::BUYER_ENDPOINT,
        ], (new PeppolBillingDraftRules())->violations($buyerDraft));
    }

    public function testReportsTheMainDraftRequirements(): void
    {
        $draft = $this->draft(
            $this->party(null, 'SK'),
            $this->party('0088:buyer-1', 'SK'),
            [],
            'ZZZ'
        )->withReferences(
            UblDraftReferences::none()
                    ->withBuyerReference(null)
        )->withDates(
            UblDraftDates::none()
                    ->withDueDate(null)
        );

        $this->assertSame([
            PeppolBillingDraftRule::CURRENCY,
            PeppolBillingDraftRule::SELLER_ENDPOINT,
            PeppolBillingDraftRule::BUYER_REFERENCE,
            PeppolBillingDraftRule::PAYMENT_DUE_DATE_OR_TERMS,
            PeppolBillingDraftRule::INVOICE_LINES,
        ], (new PeppolBillingDraftRules())->violations($draft));
    }

    public function testRejectsUnknownUnitsAndCurrencies(): void
    {
        $line = $this->line('C62')->withPriceBaseQuantity(
            UblDecimal::fromString('1'),
            UnitCode::fromCanonical('ZZZ')
        );
        $draft = $this->draft(null, null, [$line], 'ZZZ')
            ->withTaxCurrencyCode(CurrencyCode::fromCanonical('ZZZ'));

        $this->assertSame([
            PeppolBillingDraftRule::CURRENCY,
            PeppolBillingDraftRule::UNIT_CODE,
        ], (new PeppolBillingDraftRules())->violations($draft));
    }

    public function testRejectsTaxCurrencyMatchingTheDocumentCurrency(): void
    {
        $draft      = $this->draft()->withTaxCurrencyCode(CurrencyCode::fromCanonical('EUR'));
        $validDraft = $this->draft()->withTaxCurrencyCode(CurrencyCode::fromCanonical('USD'));

        $this->assertSame([
            PeppolBillingDraftRule::CURRENCY,
        ], (new PeppolBillingDraftRules())->violations($draft));
        $this->assertNotContains(
            PeppolBillingDraftRule::CURRENCY,
            (new PeppolBillingDraftRules())->violations($validDraft)
        );
    }

    public function testPaymentTermsSatisfyThePositiveAmountRequirementWithoutADueDate(): void
    {
        $draft = $this->draft()
                ->withDates(
                    UblDraftDates::none()
                        ->withDueDate(null)
                )
                ->withPayment(
                    UblDraftPayment::none()
                        ->withTermsNote('Net within 30 days')
                );

        $this->assertNotContains(
            PeppolBillingDraftRule::PAYMENT_DUE_DATE_OR_TERMS,
            (new PeppolBillingDraftRules())->violations($draft)
        );
    }

    public function testPositiveCreditNoteDraftDoesNotRequireDueDateOrPaymentTerms(): void
    {
        $draft = $this->draft(
            null,
            null,
            null,
            'EUR',
            '123.00',
            UblDocumentRoot::CREDIT_NOTE
        )->withDates(
            UblDraftDates::none()
                    ->withDueDate(null)
        );

        $this->assertNotContains(
            PeppolBillingDraftRule::PAYMENT_DUE_DATE_OR_TERMS,
            (new PeppolBillingDraftRules())->violations($draft)
        );
    }

    /**
     * Requires a due date only when a positive payable amount is due.
     *
     * @param string $amount          Payable amount under test.
     * @param bool   $requiresDueDate Expected due-date requirement.
     * @dataProvider payableAmountCases
     */
    public function testRequiresDueDateOnlyForPositiveValidPayableAmounts(string $amount, bool $requiresDueDate): void
    {
        $draft = $this->draft(null, null, null, 'EUR', $amount)->withDates(
            UblDraftDates::none()
                    ->withDueDate(null)
        );
        $violations = (new PeppolBillingDraftRules())->violations($draft);

        $this->assertSame(
            $requiresDueDate,
            in_array(PeppolBillingDraftRule::PAYMENT_DUE_DATE_OR_TERMS, $violations, true)
        );
    }

    /**
     * Provides payable amounts and their expected due-date requirement.
     *
     * @return array<array-key, array<array-key, mixed>> Amount and expectation cases.
     */
    public function payableAmountCases(): array
    {
        return [
            'positive amount'         => ['123.00', true],
            'small positive amount'   => ['0.01', true],
            'trimmed positive amount' => [' 123.00 ', true],
            'zero amount'             => ['0.00', false],
            'negative amount'         => ['-123.00', false],
            'negative zero'           => ['-0.00', false],
            'too many decimal places' => ['123.001', false],
            'large positive amount'   => ['12345678901234567', true],
        ];
    }

    public function testRejectsDifferentPriceBaseUnitFromTheInvoiceLineUnit(): void
    {
        $line = $this->line('C62')->withPriceBaseQuantity(
            UblDecimal::fromString('1'),
            UnitCode::fromCanonical('KGM')
        );
        $draft = $this->draft(null, null, [$line]);

        $this->assertContains(
            PeppolBillingDraftRule::UNIT_CODE,
            (new PeppolBillingDraftRules())->violations($draft)
        );
    }

    public function testRejectsAUnitCodeAbsentFromTheCanonicalList(): void
    {
        $draft = $this->draft(null, null, [$this->line('KS')]);

        $this->assertContains(
            PeppolBillingDraftRule::UNIT_CODE,
            (new PeppolBillingDraftRules())->violations($draft)
        );
    }

    public function testAllowsMultipleNotesOnlyForGermanParties(): void
    {
        $draft       = $this->draft()->withAddedNote('First')->withAddedNote('Second');
        $germanDraft = $this->draft(
            $this->party('0088:seller-1', 'DE'),
            $this->party('0088:buyer-1', 'DE')
        )->withAddedNote('First')->withAddedNote('Second');

        $this->assertContains(
            PeppolBillingDraftRule::DOCUMENT_NOTE_COUNT,
            (new PeppolBillingDraftRules())->violations($draft)
        );
        $this->assertNotContains(
            PeppolBillingDraftRule::DOCUMENT_NOTE_COUNT,
            (new PeppolBillingDraftRules())->violations($germanDraft)
        );
    }

    public function testGermanNoteExceptionUsesPostalCountriesRatherThanVatPrefixes(): void
    {
        $draft = $this->draft(
            $this->party('0088:seller-1', 'SK', 'DE'),
            $this->party('0088:buyer-1', 'SK', 'DE')
        )->withAddedNote('First')->withAddedNote('Second');

        $this->assertContains(
            PeppolBillingDraftRule::DOCUMENT_NOTE_COUNT,
            (new PeppolBillingDraftRules())->violations($draft)
        );
    }

    public function testRequiresAtLeastOneLineAndRejectsUnknownCountryCodes(): void
    {
        $draft = $this->draft($this->party('0088:seller-1', 'ZZ'), null, []);

        $this->assertSame([
            PeppolBillingDraftRule::SELLER_COUNTRY,
            PeppolBillingDraftRule::INVOICE_LINES,
        ], (new PeppolBillingDraftRules())->violations($draft));
    }

    public function testChecksCountryCodesInOptionalAddressesAndLineOrigin(): void
    {
        $unknownCountry = new UblDraftAddress(CountryCode::fromCanonical('ZZ'));
        $draft          = $this->draft()
            ->withDelivery(new UblDraftDelivery(null, null, $unknownCountry))
            ->withTaxRepresentative(new UblDraftTaxRepresentative(
                'Synthetic representative',
                $unknownCountry,
                new UblDraftIdentifier('ZZ123')
            ))
            ->withLines([$this->line('C62')->withOriginCountryCode(CountryCode::fromCanonical('ZZ'))]);

        $this->assertSame([
            PeppolBillingDraftRule::DELIVERY_COUNTRY,
            PeppolBillingDraftRule::TAX_REPRESENTATIVE_COUNTRY,
            PeppolBillingDraftRule::ORIGIN_COUNTRY,
        ], (new PeppolBillingDraftRules())->violations($draft));
    }

    public function testChecksLineOriginAndUnitRulesAcrossAllLinesInDeclarationOrder(): void
    {
        $draft = $this->draft(null, null, [
            $this->line('C62')->withOriginCountryCode(CountryCode::fromCanonical('ZZ')),
            $this->line('ZZZ'),
        ]);

        $this->assertSame([
            PeppolBillingDraftRule::ORIGIN_COUNTRY,
            PeppolBillingDraftRule::UNIT_CODE,
        ], (new PeppolBillingDraftRules())->violations($draft));
    }

    public function testSharesOnlyThePaymentIdentifierWithTheHeaderRules(): void
    {
        $this->assertSame(
            PeppolBillingRule::PAYMENT_DUE_DATE_OR_TERMS,
            PeppolBillingDraftRule::PAYMENT_DUE_DATE_OR_TERMS
        );
    }

    public function testThrowsWithAllViolatedRuleIdentifiers(): void
    {
        $draft = $this->draft(null, null, [], 'ZZZ');

        try {
            (new PeppolBillingDraftRules())->assertSatisfied($draft);
            $this->fail('An invalid draft must not meet the Peppol preflight rules.');
        } catch (PeppolBillingDraftRulesException $exception) {
            $violatedRules = [
                PeppolBillingDraftRule::CURRENCY,
                PeppolBillingDraftRule::INVOICE_LINES,
            ];

            $this->assertSame($violatedRules, $exception->violatedRules());
            $this->assertSame(
                'The UBL draft does not meet the Peppol BIS Billing 3.0 rules: '
                    . implode(', ', $violatedRules) . '.',
                $exception->getMessage()
            );
        }
    }

    /**
     * Builds a valid invoice draft with optional parties, lines, currency, amount, and root type.
     *
     * @param UblDraftLine[]|null $lines Draft lines, or null to use the standard synthetic line.
     */
    private function draft(
        ?UblDraftParty $supplier = null,
        ?UblDraftParty $customer = null,
        ?array $lines = null,
        string $currency = 'EUR',
        string $payableAmount = '123.00',
        string $root = UblDocumentRoot::INVOICE
    ): UblDocumentDraft {
        $draft = (new UblDocumentDraft(
            UblDocumentRoot::fromString($root),
            UblDocumentNumber::fromString('INV-1'),
            LocalDate::fromString('2026-11-10'),
            CurrencyCode::fromCanonical($currency),
            $supplier ?? $this->party('0088:seller-1', 'SK'),
            $customer ?? $this->party('0088:buyer-1', 'SK'),
            new UblDraftTotals(
                UblDecimal::fromString('100.00'),
                UblDecimal::fromString('100.00'),
                UblDecimal::fromString('123.00'),
                null,
                UblDecimal::fromString($payableAmount),
                UblDecimal::fromString('23.00'),
                []
            )
        ))->withReferences(
            UblDraftReferences::none()
                    ->withBuyerReference('BUYER-1')
        )->withDates(
            UblDraftDates::none()
                    ->withDueDate(LocalDate::fromString('2026-12-10'))
        );

        return $draft->withLines($lines ?? [$this->line('C62')]);
    }

    /**
     * Builds a synthetic party with an optional electronic endpoint and VAT tax scheme.
     *
     * @param string|null $endpoint   Scheme:value endpoint, or null for an unqualified identifier.
     * @param string      $country    Party address country code.
     * @param string|null $vatCountry VAT registration country, or null to derive it from the address country.
     */
    private function party(?string $endpoint, string $country, ?string $vatCountry = null): UblDraftParty
    {
        $endpointIdentifier = $endpoint === null
            ? UblEndpointIdentifier::fromSchemeAndValue(null, 'urn:example:unqualified')
            : $this->endpointIdentifier($endpoint);
        $address = (new UblDraftAddress(CountryCode::fromCanonical($country)))
            ->withLines('Street 1')
            ->withLocality('City', '00000');
        $party = (new UblDraftParty($endpointIdentifier, 'Synthetic party', CountryCode::fromCanonical($country)))
            ->withAddress($address);

        $vatCountry = $vatCountry ?? ($country === 'SK' ? 'SK' : null);

        return $vatCountry === null
            ? $party
            : $party->withTaxScheme(new UblDraftPartyTaxScheme(
                new UblDraftIdentifier($vatCountry . '1234567890'),
                TaxSchemeCode::fromString(TaxSchemeCode::VAT)
            ));
    }

    /** Parses a scheme:value endpoint identifier or preserves a value without a scheme. */
    private function endpointIdentifier(string $identifier): UblEndpointIdentifier
    {
        $parts = explode(':', $identifier, 2);

        return count($parts) === 2
            ? UblEndpointIdentifier::fromSchemeAndValue($parts[0], $parts[1])
            : UblEndpointIdentifier::fromSchemeAndValue(null, $parts[0]);
    }

    /** Builds a party with an address and endpoint identifier but no VAT tax scheme. */
    private function partyWithoutTax(string $endpoint, string $country): UblDraftParty
    {
        $address = (new UblDraftAddress(CountryCode::fromCanonical($country)))
            ->withLines('Street 1')
            ->withLocality('City', '00000');

        return (new UblDraftParty(
            $this->endpointIdentifier($endpoint),
            'Synthetic party',
            CountryCode::fromCanonical($country)
        ))
            ->withAddress($address);
    }

    /** Builds a synthetic two-unit line using the selected canonical unit code and standard VAT. */
    private function line(string $unitCode): UblDraftLine
    {
        return new UblDraftLine(
            new UblDraftIdentifier('1'),
            UblDecimal::fromString('2.0000'),
            UnitCode::fromCanonical($unitCode),
            UblDecimal::fromString('100.00'),
            'Synthetic item',
            new UblDraftTaxCategory(TaxCategoryCode::fromString('S'), UblDecimal::fromString('23')),
            UblDecimal::fromString('50.00')
        );
    }
}
