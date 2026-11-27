<?php

namespace DreaM1\EfakturaConnector\Tests\Unit\Domain\Ubl;

use DreaM1\EfakturaConnector\Domain\Ubl\CodeList\PaymentMeansCode;
use DreaM1\EfakturaConnector\Domain\Ubl\Draft\UblDocumentNumber;
use DreaM1\EfakturaConnector\Domain\Ubl\Draft\UblDraftDates;
use DreaM1\EfakturaConnector\Domain\Ubl\Draft\UblDraftIdentifier;
use DreaM1\EfakturaConnector\Domain\Ubl\Draft\UblDraftPayment;
use DreaM1\EfakturaConnector\Domain\Ubl\Draft\UblDraftPaymentMeans;
use DreaM1\EfakturaConnector\Domain\Ubl\Draft\UblDraftPeriod;
use DreaM1\EfakturaConnector\Domain\Ubl\Draft\UblDraftReferences;
use DreaM1\EfakturaConnector\Domain\Ubl\LocalDate;
use PHPUnit\Framework\TestCase;

class UblDraftGroupsTest extends TestCase
{
    public function testDatesAreImmutableAndIndependent(): void
    {
        $none  = UblDraftDates::none();
        $dates = $none
            ->withDueDate(LocalDate::fromString('2026-12-10'))
            ->withTaxPointDate(LocalDate::fromString('2026-11-10'))
            ->withDeliveryDate(LocalDate::fromString('2026-11-12'))
            ->withInvoicePeriod(new UblDraftPeriod(
                LocalDate::fromString('2026-11-01'),
                LocalDate::fromString('2026-11-30')
            ));

        $this->assertNull($none->dueDate());
        $this->assertNull($none->invoicePeriod());
        $this->assertSame('2026-12-10', $dates->dueDate()->value());
        $this->assertSame('2026-11-10', $dates->taxPointDate()->value());
        $this->assertSame('2026-11-12', $dates->deliveryDate()->value());
        $this->assertNotNull($dates->invoicePeriod());

        $cleared = $dates->withDueDate(null);

        $this->assertNull($cleared->dueDate());
        $this->assertSame('2026-11-10', $cleared->taxPointDate()->value());
        $this->assertSame('2026-12-10', $dates->dueDate()->value());
    }

    public function testReferencesNormalizeBlankTextAndReplaceTheBillingReference(): void
    {
        $references = UblDraftReferences::none()
            ->withBuyerReference('')
            ->withAccountingCost(' ')
            ->withOrderReference(new UblDraftIdentifier('ORDER-1'))
            ->withSalesOrderReference(new UblDraftIdentifier('SALES-1'))
            ->withBillingReference(UblDocumentNumber::fromString('INV-1'), LocalDate::fromString('2026-10-01'))
            ->withBillingReference(UblDocumentNumber::fromString('INV-2'));

        $this->assertNull($references->buyerReference());
        $this->assertSame(' ', $references->accountingCost());
        $this->assertSame('ORDER-1', $references->orderReference()->value());
        $this->assertSame('SALES-1', $references->salesOrderReference()->value());
        $this->assertSame('INV-2', $references->billingReference()->value());
        $this->assertNull($references->billingReferenceIssueDate());
        $this->assertCount(1, $references->documentReferences());
        $this->assertNull(UblDraftReferences::none()->billingReference());
        $this->assertSame([], UblDraftReferences::none()->documentReferences());
    }

    public function testPaymentReplacesOrAppendsMeansAndNormalizesTheTermsNote(): void
    {
        $first   = new UblDraftPaymentMeans(PaymentMeansCode::fromString('30'), new UblDraftIdentifier('first'));
        $second  = new UblDraftPaymentMeans(PaymentMeansCode::fromString('42'), new UblDraftIdentifier('second'));
        $payment = UblDraftPayment::none()
            ->withPaymentMeans($first)
            ->withAdditionalPaymentMeans($second)
            ->withTermsNote('Net 30')
            ->withTermsNote('');

        $this->assertSame([$first, $second], $payment->paymentMeansList());
        $this->assertSame($first, $payment->paymentMeans());
        $this->assertNull($payment->termsNote());
        $this->assertNull($payment->payee());
        $this->assertSame([$second], $payment->withPaymentMeans($second)->paymentMeansList());
        $this->assertNull(UblDraftPayment::none()->paymentMeans());
        $this->assertSame([$first, $second], $payment->paymentMeansList());
    }
}
