<?php

namespace DreaM1\EfakturaConnector\Tests\Unit\Infrastructure\Ubl;

use DreaM1\EfakturaConnector\Core\Ubl\Exceptions\PeppolBillingRulesException;
use DreaM1\EfakturaConnector\Core\Ubl\Exceptions\UblValidationException;
use DreaM1\EfakturaConnector\Core\Ubl\Validation\PeppolBillingRule;
use DreaM1\EfakturaConnector\Domain\Ubl\PeppolBillingProfile;
use DreaM1\EfakturaConnector\Domain\Ubl\UblDocumentHeader;
use DreaM1\EfakturaConnector\Domain\Ubl\UblDocumentRoot;
use DreaM1\EfakturaConnector\Infrastructure\Ubl\Validation\PeppolBillingHeaderRules;
use PHPUnit\Framework\TestCase;

/** Tests the standalone Peppol Billing header rule that complements Schematron. */
class PeppolBillingHeaderRulesTest extends TestCase
{
    public function testDoesNotRepeatRulesCoveredBySchematron(): void
    {
        $header = $this->header([
            'customizationId'        => 'urn:example:unsupported',
            'processId'              => 'urn:example:unsupported',
            'number'                 => null,
            'issueDate'              => 'not-a-date',
            'dueDate'                => 'not-a-date',
            'taxPointDate'           => 'not-a-date',
            'documentTypeCode'       => null,
            'currency'               => 'ZZZ',
            'payableAmount'          => '100.001',
            'supplierEndpointScheme' => '9999',
            'customerEndpointValue'  => null,
            'buyerReference'         => null,
        ]);

        $this->assertSame([], (new PeppolBillingHeaderRules())->violations($header));
    }

    public function testReportsBrCo25ForPositiveInvoiceWithoutDueDateOrPaymentTerms(): void
    {
        $header = $this->header([
            'dueDate'         => null,
            'hasPaymentTerms' => false,
        ]);

        $this->assertSame(
            [PeppolBillingRule::PAYMENT_DUE_DATE_OR_TERMS],
            (new PeppolBillingHeaderRules())->violations($header)
        );
    }

    public function testDoesNotReportBrCo25ForCreditNoteOrUnknownRoot(): void
    {
        $rules = new PeppolBillingHeaderRules();

        $creditNote = $this->header([
            'documentRoot'     => UblDocumentRoot::CREDIT_NOTE,
            'documentTypeCode' => '381',
            'dueDate'          => null,
            'hasPaymentTerms'  => false,
        ]);
        $unknownRoot = $this->header([
            'documentRoot'    => null,
            'dueDate'         => null,
            'hasPaymentTerms' => false,
        ]);

        $this->assertSame([], $rules->violations($creditNote));
        $this->assertSame([], $rules->violations($unknownRoot));
    }

    public function testPaymentTermsOrNonPositiveAmountSatisfiesBrCo25(): void
    {
        $rules = new PeppolBillingHeaderRules();

        $withPaymentTerms = $this->header([
            'dueDate'         => null,
            'hasPaymentTerms' => true,
        ]);
        $zeroAmount = $this->header([
            'dueDate'       => null,
            'payableAmount' => '0.00',
        ]);
        $negativeAmount = $this->header([
            'dueDate'       => null,
            'payableAmount' => '-10.00',
        ]);

        $this->assertSame([], $rules->violations($withPaymentTerms));
        $this->assertSame([], $rules->violations($zeroAmount));
        $this->assertSame([], $rules->violations($negativeAmount));
    }

    public function testExceptionContainsOnlyBrCo25Violation(): void
    {
        $this->expectException(PeppolBillingRulesException::class);

        try {
            (new PeppolBillingHeaderRules())->assertSatisfied($this->header([
                'dueDate'         => null,
                'hasPaymentTerms' => false,
            ]));
        } catch (PeppolBillingRulesException $exception) {
            $this->assertInstanceOf(UblValidationException::class, $exception);
            $this->assertSame(
                'The document does not meet the Peppol BIS Billing 3.0 rules: '
                    . PeppolBillingRule::PAYMENT_DUE_DATE_OR_TERMS . '.',
                $exception->getMessage()
            );
            $this->assertSame(
                [PeppolBillingRule::PAYMENT_DUE_DATE_OR_TERMS],
                $exception->violatedRules()
            );

            throw $exception;
        }
    }

    /** @param array<string, mixed> $changes */
    private function header(array $changes = []): UblDocumentHeader
    {
        return UblDocumentHeader::fromArray(array_merge([
            'number'                 => 'INV-SYNTHETIC-1',
            'customizationId'        => PeppolBillingProfile::CUSTOMIZATION_ID,
            'processId'              => PeppolBillingProfile::PROCESS_ID,
            'documentTypeCode'       => '380',
            'documentRoot'           => UblDocumentRoot::INVOICE,
            'supplierEndpointScheme' => '0088',
            'supplierEndpointValue'  => 'synthetic-sender',
            'customerEndpointScheme' => '0088',
            'customerEndpointValue'  => 'synthetic-receiver',
            'issueDate'              => '2026-11-10',
            'dueDate'                => '2026-12-10',
            'currency'               => 'EUR',
            'payableAmount'          => '100.00',
            'buyerReference'         => 'SYNTHETIC-REFERENCE',
        ], $changes));
    }
}
