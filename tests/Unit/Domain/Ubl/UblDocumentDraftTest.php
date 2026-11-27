<?php

namespace DreaM1\EfakturaConnector\Tests\Unit\Domain\Ubl;

use DreaM1\EfakturaConnector\Domain\EFaktura\PeppolCustomizationIdentifier;
use DreaM1\EfakturaConnector\Domain\EFaktura\PeppolProcessIdentifier;
use DreaM1\EfakturaConnector\Domain\Ubl\Code\CountryCode;
use DreaM1\EfakturaConnector\Domain\Ubl\Code\CurrencyCode;
use DreaM1\EfakturaConnector\Domain\Ubl\Code\UnitCode;
use DreaM1\EfakturaConnector\Domain\Ubl\CodeList\PaymentMeansCode;
use DreaM1\EfakturaConnector\Domain\Ubl\CodeList\TaxCategoryCode;
use DreaM1\EfakturaConnector\Domain\Ubl\CodeList\UblDocumentTypeCode;
use DreaM1\EfakturaConnector\Domain\Ubl\Draft\UblDecimal;
use DreaM1\EfakturaConnector\Domain\Ubl\Draft\UblDocumentDraft;
use DreaM1\EfakturaConnector\Domain\Ubl\Draft\UblDocumentNumber;
use DreaM1\EfakturaConnector\Domain\Ubl\Draft\UblDocumentReferenceType;
use DreaM1\EfakturaConnector\Domain\Ubl\Draft\UblDraftAddress;
use DreaM1\EfakturaConnector\Domain\Ubl\Draft\UblDraftDates;
use DreaM1\EfakturaConnector\Domain\Ubl\Draft\UblDraftDocumentReference;
use DreaM1\EfakturaConnector\Domain\Ubl\Draft\UblDraftIdentifier;
use DreaM1\EfakturaConnector\Domain\Ubl\Draft\UblDraftLine;
use DreaM1\EfakturaConnector\Domain\Ubl\Draft\UblDraftParty;
use DreaM1\EfakturaConnector\Domain\Ubl\Draft\UblDraftPayment;
use DreaM1\EfakturaConnector\Domain\Ubl\Draft\UblDraftPaymentMeans;
use DreaM1\EfakturaConnector\Domain\Ubl\Draft\UblDraftReferences;
use DreaM1\EfakturaConnector\Domain\Ubl\Draft\UblDraftTaxCategory;
use DreaM1\EfakturaConnector\Domain\Ubl\Draft\UblDraftTotals;
use DreaM1\EfakturaConnector\Domain\Ubl\LocalDate;
use DreaM1\EfakturaConnector\Domain\Ubl\PeppolBillingProfile;
use DreaM1\EfakturaConnector\Domain\Ubl\UblDocumentRoot;
use DreaM1\EfakturaConnector\Domain\Ubl\UblEndpointIdentifier;
use PHPUnit\Framework\TestCase;

/** Covers immutable invoice and credit-note drafts, typed values and copy-returning builder methods. */
class UblDocumentDraftTest extends TestCase
{
    public function testWithMethodsReturnAnImmutableDraftAndNormalizeOptionalText(): void
    {
        $original = $this->draft(UblDocumentRoot::INVOICE);
        $line     = new UblDraftLine(
            new UblDraftIdentifier('1'),
            UblDecimal::fromString('2.0000'),
            UnitCode::fromCanonical('C62'),
            UblDecimal::fromString('10.00'),
            'Item',
            new UblDraftTaxCategory(TaxCategoryCode::fromString('S'), UblDecimal::fromString('23')),
            UblDecimal::fromString('5.00')
        );
        $line = $line
            ->withOriginCountryCode(CountryCode::fromCanonical('SK'))
            ->withPriceBaseQuantity(UblDecimal::fromString('1'), UnitCode::fromCanonical('C62'));
        $paymentMeans = new UblDraftPaymentMeans(
            PaymentMeansCode::fromString('30'),
            new UblDraftIdentifier('VS-1'),
            new UblDraftIdentifier('SK0000000000000000000000')
        );

        $changed = $original

                ->withTypeCode(UblDocumentTypeCode::fromString('384'))
                ->withSpecification(
                    PeppolCustomizationIdentifier::fromString('customization-id'),
                    PeppolProcessIdentifier::fromString('profile-id')
                )
                ->withDates(
                    UblDraftDates::none()
                        ->withDueDate(null)
                        ->withTaxPointDate(LocalDate::fromString('2026-11-20'))
                        ->withDeliveryDate(LocalDate::fromString('2026-11-21'))
                )
                ->withTaxCurrencyCode(CurrencyCode::fromCanonical('USD'))
                ->withReferences(
                    UblDraftReferences::none()
                        ->withBuyerReference('buyer-1')
                        ->withOrderReference(new UblDraftIdentifier('order-1'))
                        ->withBillingReference(UblDocumentNumber::fromString('original-1'), LocalDate::fromString('2026-10-01'))
                )
                ->withAddedNote('first note')
                ->withPayment(
                    UblDraftPayment::none()
                        ->withPaymentMeans($paymentMeans)
                )
                ->withLines([8 => $line]);

        $this->assertSame('380', $original->typeCode()->value());
        $this->assertSame(PeppolBillingProfile::CUSTOMIZATION_ID, $original->customizationId()->value());
        $this->assertSame(PeppolBillingProfile::PROCESS_ID, $original->profileId()->value());
        $this->assertNull($original->dates()->dueDate());
        $this->assertSame([], $original->notes());
        $this->assertNull($original->references()->billingReference());
        $this->assertNull($original->payment()->paymentMeans());
        $this->assertSame([], $original->lines());

        $this->assertSame('384', $changed->typeCode()->value());
        $this->assertSame('customization-id', $changed->customizationId()->value());
        $this->assertSame('profile-id', $changed->profileId()->value());
        $this->assertNull($changed->dates()->dueDate());
        $this->assertSame('2026-11-20', $changed->dates()->taxPointDate()->value());
        $this->assertSame('2026-11-21', $changed->dates()->deliveryDate()->value());
        $this->assertNull($original->taxCurrencyCode());
        $this->assertSame('USD', $changed->taxCurrencyCode()->value());
        $this->assertSame('buyer-1', $changed->references()->buyerReference());
        $this->assertSame('order-1', $changed->references()->orderReference()->value());
        $this->assertSame(['first note'], $changed->notes());
        $this->assertInstanceOf(UblDocumentNumber::class, $changed->references()->billingReference());
        $this->assertSame('original-1', $changed->references()->billingReference()->value());
        $this->assertSame('2026-10-01', $changed->references()->billingReferenceIssueDate()->value());
        $this->assertSame($paymentMeans, $changed->payment()->paymentMeans());
        $this->assertSame([$line], $changed->lines());
        $this->assertSame('SK', $line->originCountryCode()->value());
        $this->assertSame('C62', $line->priceBaseUnitCode()->value());
    }

    public function testCreditNoteUsesCreditNoteTypeAndCanBeRecognized(): void
    {
        $draft = $this->draft(UblDocumentRoot::CREDIT_NOTE);

        $this->assertSame(UblDocumentRoot::CREDIT_NOTE, $draft->kind()->value());
        $this->assertTrue($draft->isCreditNote());
        $this->assertSame('381', $draft->typeCode()->value());
        $this->assertFalse($this->draft(UblDocumentRoot::INVOICE)->isCreditNote());
    }

    public function testOptionalDraftTextMapsEmptyStringsToNullWithoutTrimming(): void
    {
        $address = (new UblDraftAddress(CountryCode::fromCanonical('SK')))
            ->withLines('', null, null)
            ->withLocality(' ', null);
        $party = (new UblDraftParty(
            UblEndpointIdentifier::fromSchemeAndValue('0245', 'sender'),
            'Synthetic party',
            CountryCode::fromCanonical('SK')
        ))->withAddress($address);
        $paymentMeans = new UblDraftPaymentMeans(
            PaymentMeansCode::fromString('30'),
            null,
            new UblDraftIdentifier(' '),
            ''
        );
        $taxCategory      = new UblDraftTaxCategory(TaxCategoryCode::fromString('E'), null, null, ' ');
        $emptyScheme      = new UblDraftIdentifier('company-id', '');
        $whitespaceScheme = new UblDraftIdentifier('company-id', ' ');
        $draft            = $this->draft(UblDocumentRoot::INVOICE)

                ->withReferences(
                    UblDraftReferences::none()
                        ->withBuyerReference('')
                        ->withOrderReference(new UblDraftIdentifier(' '))
                        ->withBillingReference(UblDocumentNumber::fromString('invoice-1'))
                );

        $this->assertNull($party->address()->street());
        $this->assertSame(' ', $party->address()->city());
        $this->assertNull($party->address()->postalZone());
        $this->assertNull($paymentMeans->paymentId());
        $this->assertSame(' ', $paymentMeans->accountId()->value());
        $this->assertNull($paymentMeans->accountName());
        $this->assertNull($taxCategory->exemptionReasonCode());
        $this->assertSame(' ', $taxCategory->exemptionReason());
        $this->assertNull($emptyScheme->scheme());
        $this->assertSame(' ', $whitespaceScheme->scheme());
        $this->assertNull($draft->references()->buyerReference());
        $this->assertSame(' ', $draft->references()->orderReference()->value());
        $this->assertNull($draft->references()->billingReferenceIssueDate());
    }

    public function testAddressIsSharedAndPartyAddressUpdatesPreserveOtherFields(): void
    {
        $address = (new UblDraftAddress(CountryCode::fromCanonical('SK')))
            ->withLines('Street 1')
            ->withLocality('City', '00000')
            ->withLines('Street 2', 'Building', 'Floor 2')
            ->withLocality('New City', '11111', 'Region');
        $party = (new UblDraftParty(
            UblEndpointIdentifier::fromSchemeAndValue('0245', 'sender'),
            'Synthetic party',
            CountryCode::fromCanonical('SK')
        ))->withAddress($address);

        $this->assertInstanceOf(UblDraftAddress::class, $party->address());
        $this->assertSame('Street 2', $party->address()->street());
        $this->assertSame('Building', $party->address()->additionalStreet());
        $this->assertSame('Floor 2', $party->address()->addressLine());
        $this->assertSame('New City', $party->address()->city());
        $this->assertSame('11111', $party->address()->postalZone());
        $this->assertSame('Region', $party->address()->countrySubentity());
        $this->assertSame('SK', $party->address()->countryCode()->value());
    }

    public function testTypedCodeInputsAreStoredForSerialization(): void
    {
        $party = new UblDraftParty(
            UblEndpointIdentifier::fromSchemeAndValue('0245', 'sender'),
            'Synthetic party',
            CountryCode::fromCanonical('SK')
        );
        $draft = new UblDocumentDraft(
            UblDocumentRoot::fromString(UblDocumentRoot::INVOICE),
            UblDocumentNumber::fromString('INV-1'),
            LocalDate::fromString('2026-11-20'),
            CurrencyCode::fromCanonical('EUR'),
            $party,
            $party,
            new UblDraftTotals(
                UblDecimal::fromString('10.00'),
                UblDecimal::fromString('10.00'),
                UblDecimal::fromString('12.30'),
                null,
                UblDecimal::fromString('12.30'),
                UblDecimal::fromString('2.30'),
                []
            )
        );
        $line = new UblDraftLine(
            new UblDraftIdentifier('1'),
            UblDecimal::fromString('1'),
            UnitCode::fromCanonical('H87'),
            UblDecimal::fromString('1.00'),
            'Item',
            new UblDraftTaxCategory(TaxCategoryCode::fromString('S'), UblDecimal::fromString('23')),
            UblDecimal::fromString('1.00')
        );

        $this->assertSame('EUR', $draft->currency()->value());
        $this->assertSame('SK', $party->address()->countryCode()->value());
        $this->assertSame('H87', $line->unitCode()->value());
    }

    public function testEndpointValuePreservesSchemeAndValue(): void
    {
        $endpoint = UblEndpointIdentifier::fromSchemeAndValue('0245', 'sender');
        $party    = new UblDraftParty(
            $endpoint,
            'Synthetic party',
            CountryCode::fromCanonical('SK')
        );

        $this->assertSame($endpoint, $party->endpoint());
        $this->assertSame('0245:sender', $party->endpoint()->identifier());
    }

    public function testEndpointIdentifierPreservesAnExplicitEmptyScheme(): void
    {
        $endpoint = UblEndpointIdentifier::fromSchemeAndValue('', ' sender ');

        $this->assertSame('', $endpoint->scheme());
        $this->assertSame(' sender ', $endpoint->value());
    }

    public function testBillingReferenceDelegatesToGenericDocumentReferencesAndRemainsASetter(): void
    {
        $draft = $this->draft(UblDocumentRoot::INVOICE)

                ->withReferences(
                    UblDraftReferences::none()
                        ->withDocumentReference(new UblDraftDocumentReference(
                            UblDocumentReferenceType::fromString(UblDocumentReferenceType::ADDITIONAL),
                            new UblDraftIdentifier('ATT-1')
                        ))
                        ->withBillingReference(UblDocumentNumber::fromString('INV-OLD'))
                        ->withBillingReference(UblDocumentNumber::fromString('INV-NEW'))
                );

        $this->assertSame('INV-NEW', $draft->references()->billingReference()->value());
        $this->assertSame(
            [UblDocumentReferenceType::ADDITIONAL, UblDocumentReferenceType::BILLING],
            array_map(static function (UblDraftDocumentReference $reference): string {
                return $reference->type()->value();
            }, $draft->references()->documentReferences())
        );
        $this->assertSame('ATT-1', $draft->references()->documentReferences()[0]->id()->value());
    }

    public function testWithPaymentMeansReplacesAndAdditionalMethodAppends(): void
    {
        $first  = new UblDraftPaymentMeans(PaymentMeansCode::fromString('30'), new UblDraftIdentifier('first'));
        $second = new UblDraftPaymentMeans(PaymentMeansCode::fromString('42'), new UblDraftIdentifier('second'));
        $third  = new UblDraftPaymentMeans(PaymentMeansCode::fromString('48'), new UblDraftIdentifier('third'));
        $draft  = $this->draft(UblDocumentRoot::INVOICE)

                ->withPayment(
                    UblDraftPayment::none()
                        ->withPaymentMeans($first)
                        ->withAdditionalPaymentMeans($second)
                        ->withPaymentMeans($third)
                        ->withAdditionalPaymentMeans($second)
                );

        $this->assertSame([$third, $second], $draft->payment()->paymentMeansList());
        $this->assertSame($third, $draft->payment()->paymentMeans());
    }

    private function draft(string $kind): UblDocumentDraft
    {
        $party = static function (string $identifier): UblDraftParty {
            $parts    = explode(':', $identifier, 2);
            $endpoint = count($parts) === 2
                ? UblEndpointIdentifier::fromSchemeAndValue($parts[0], $parts[1])
                : UblEndpointIdentifier::fromSchemeAndValue(null, $parts[0]);

            return new UblDraftParty(
                $endpoint,
                'Synthetic party',
                CountryCode::fromCanonical('SK')
            );
        };

        return new UblDocumentDraft(
            UblDocumentRoot::fromString($kind),
            UblDocumentNumber::fromString('INV-1'),
            LocalDate::fromString('2026-11-20'),
            CurrencyCode::fromCanonical('EUR'),
            $party('0245:sender'),
            $party('0245:receiver'),
            new UblDraftTotals(
                UblDecimal::fromString('10.00'),
                UblDecimal::fromString('10.00'),
                UblDecimal::fromString('12.30'),
                null,
                UblDecimal::fromString('12.30'),
                UblDecimal::fromString('2.30'),
                []
            )
        );
    }
}
