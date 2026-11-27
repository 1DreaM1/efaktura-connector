<?php

namespace DreaM1\EfakturaConnector\Tests\Unit\Infrastructure\Ubl;

use DOMDocument;
use DOMElement;
use DreaM1\EfakturaConnector\Core\Ubl\Exceptions\SchemaValidationException;
use DreaM1\EfakturaConnector\Domain\Ubl\Code\CountryCode;
use DreaM1\EfakturaConnector\Domain\Ubl\Code\CurrencyCode;
use DreaM1\EfakturaConnector\Domain\Ubl\Code\UnitCode;
use DreaM1\EfakturaConnector\Domain\Ubl\CodeList\AdditionalDocumentTypeCode;
use DreaM1\EfakturaConnector\Domain\Ubl\CodeList\AllowanceChargeReasonCode;
use DreaM1\EfakturaConnector\Domain\Ubl\CodeList\DocumentTypeCode;
use DreaM1\EfakturaConnector\Domain\Ubl\CodeList\PartyIdentifierScheme;
use DreaM1\EfakturaConnector\Domain\Ubl\CodeList\PaymentMeansCode;
use DreaM1\EfakturaConnector\Domain\Ubl\CodeList\TaxCategoryCode;
use DreaM1\EfakturaConnector\Domain\Ubl\CodeList\TaxSchemeCode;
use DreaM1\EfakturaConnector\Domain\Ubl\CodeList\UblDocumentTypeCode;
use DreaM1\EfakturaConnector\Domain\Ubl\CodeList\VatExemptionReasonCode;
use DreaM1\EfakturaConnector\Domain\Ubl\Draft\Base64DocumentContent;
use DreaM1\EfakturaConnector\Domain\Ubl\Draft\EmailAddress;
use DreaM1\EfakturaConnector\Domain\Ubl\Draft\ExternalDocumentUri;
use DreaM1\EfakturaConnector\Domain\Ubl\Draft\MimeType;
use DreaM1\EfakturaConnector\Domain\Ubl\Draft\UblDecimal;
use DreaM1\EfakturaConnector\Domain\Ubl\Draft\UblDocumentDraft;
use DreaM1\EfakturaConnector\Domain\Ubl\Draft\UblDocumentNumber;
use DreaM1\EfakturaConnector\Domain\Ubl\Draft\UblDocumentReferenceType;
use DreaM1\EfakturaConnector\Domain\Ubl\Draft\UblDraftAddress;
use DreaM1\EfakturaConnector\Domain\Ubl\Draft\UblDraftAllowanceCharge;
use DreaM1\EfakturaConnector\Domain\Ubl\Draft\UblDraftCommodityClassification;
use DreaM1\EfakturaConnector\Domain\Ubl\Draft\UblDraftContact;
use DreaM1\EfakturaConnector\Domain\Ubl\Draft\UblDraftDates;
use DreaM1\EfakturaConnector\Domain\Ubl\Draft\UblDraftDelivery;
use DreaM1\EfakturaConnector\Domain\Ubl\Draft\UblDraftDocumentReference;
use DreaM1\EfakturaConnector\Domain\Ubl\Draft\UblDraftIdentifier;
use DreaM1\EfakturaConnector\Domain\Ubl\Draft\UblDraftItemProperty;
use DreaM1\EfakturaConnector\Domain\Ubl\Draft\UblDraftLine;
use DreaM1\EfakturaConnector\Domain\Ubl\Draft\UblDraftParty;
use DreaM1\EfakturaConnector\Domain\Ubl\Draft\UblDraftPartyTaxScheme;
use DreaM1\EfakturaConnector\Domain\Ubl\Draft\UblDraftPayee;
use DreaM1\EfakturaConnector\Domain\Ubl\Draft\UblDraftPayment;
use DreaM1\EfakturaConnector\Domain\Ubl\Draft\UblDraftPaymentCard;
use DreaM1\EfakturaConnector\Domain\Ubl\Draft\UblDraftPaymentMandate;
use DreaM1\EfakturaConnector\Domain\Ubl\Draft\UblDraftPaymentMeans;
use DreaM1\EfakturaConnector\Domain\Ubl\Draft\UblDraftPeriod;
use DreaM1\EfakturaConnector\Domain\Ubl\Draft\UblDraftPriceAllowance;
use DreaM1\EfakturaConnector\Domain\Ubl\Draft\UblDraftReferences;
use DreaM1\EfakturaConnector\Domain\Ubl\Draft\UblDraftTaxCategory;
use DreaM1\EfakturaConnector\Domain\Ubl\Draft\UblDraftTaxRepresentative;
use DreaM1\EfakturaConnector\Domain\Ubl\Draft\UblDraftTaxSubtotal;
use DreaM1\EfakturaConnector\Domain\Ubl\Draft\UblDraftTotals;
use DreaM1\EfakturaConnector\Domain\Ubl\LocalDate;
use DreaM1\EfakturaConnector\Domain\Ubl\UblDocumentRoot;
use DreaM1\EfakturaConnector\Domain\Ubl\UblEndpointIdentifier;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Validation\Schema\UblSchemaValidator;
use DreaM1\EfakturaConnector\Infrastructure\Ubl\Reader\UblDocumentReader;
use DreaM1\EfakturaConnector\Infrastructure\Ubl\Reader\UblXmlDocumentLoader;
use DreaM1\EfakturaConnector\Infrastructure\Ubl\Validation\PeppolBillingHeaderRules;
use DreaM1\EfakturaConnector\Infrastructure\Ubl\Writer\UblDocumentWriter;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/** Tests writing UBL invoices and credit notes. */
class UblDocumentWriterTest extends TestCase
{
    public function testWrittenInvoiceReadsBackAndMeetsTheRules(): void
    {
        $xml = (new UblDocumentWriter())->write(
            $this->draft(UblDocumentRoot::INVOICE)
                ->withAddedNote('First')
                ->withAddedNote('Second')
        );
        $document = (new UblDocumentReader(new UblXmlDocumentLoader()))->read($xml);
        $header   = $document->header();

        $this->assertSame([], (new PeppolBillingHeaderRules())->violations($header));
        $this->assertSame('INV-1', $header->number());
        $this->assertSame('380', $header->documentTypeCode());
        $this->assertSame('2026-12-10', $header->dueDate()->value());
        $this->assertSame(['First', 'Second'], $header->notes());
        $this->assertSame('0245:2012345676', $header->supplierEndpoint()->identifier());
        $supplierEndpoint = $document->content()->supplier()->endpoint();
        $customerEndpoint = $document->content()->customer()->endpoint();
        $this->assertNotNull($supplierEndpoint);
        $this->assertNotNull($customerEndpoint);
        $this->assertSame('0245', $supplierEndpoint->scheme());
        $this->assertSame('2012345676', $supplierEndpoint->value());
        $this->assertSame('0245', $customerEndpoint->scheme());
        $this->assertSame('2012345687', $customerEndpoint->value());
        $this->assertSame('123.00', $header->payableAmount());
        $this->assertSame('SK0000000000000000000000', $header->iban());
        $this->assertSame('2012345687', $document->content()->customer()->taxRegistrationId());
        $this->assertSame('2.0000', $document->content()->lines()[0]->quantity());
        $this->assertSame('100.00', $document->content()->taxSummary()[0]->taxableAmount());
    }

    public function testWrittenInvoicePreservesUnqualifiedEndpointValuesWithColons(): void
    {
        $category = new UblDraftTaxCategory(
            TaxCategoryCode::fromString(TaxCategoryCode::STANDARD),
            UblDecimal::fromString('23')
        );
        $draft = new UblDocumentDraft(
            UblDocumentRoot::fromString(UblDocumentRoot::INVOICE),
            UblDocumentNumber::fromString('INV-ENDPOINT'),
            LocalDate::fromString('2026-11-10'),
            CurrencyCode::fromCanonical('EUR'),
            new UblDraftParty(
                UblEndpointIdentifier::fromSchemeAndValue(null, 'urn:example:supplier'),
                'Synthetic Supplier',
                CountryCode::fromCanonical('SK')
            ),
            new UblDraftParty(
                UblEndpointIdentifier::fromSchemeAndValue(null, 'urn:example:customer'),
                'Synthetic Customer',
                CountryCode::fromCanonical('SK')
            ),
            $this->totals($category)
        );

        $xml = (new UblDocumentWriter())->write($draft);

        $this->assertStringContainsString(
            '<cbc:EndpointID>urn:example:supplier</cbc:EndpointID>',
            $xml
        );
        $this->assertStringContainsString(
            '<cbc:EndpointID>urn:example:customer</cbc:EndpointID>',
            $xml
        );

        $document         = (new UblDocumentReader(new UblXmlDocumentLoader()))->read($xml);
        $supplierEndpoint = $document->content()->supplier()->endpoint();
        $customerEndpoint = $document->content()->customer()->endpoint();
        $this->assertNotNull($supplierEndpoint);
        $this->assertNotNull($customerEndpoint);
        $this->assertNull($supplierEndpoint->scheme());
        $this->assertSame('urn:example:supplier', $supplierEndpoint->value());
        $this->assertNull($customerEndpoint->scheme());
        $this->assertSame('urn:example:customer', $customerEndpoint->value());
    }

    public function testWrittenDocumentsPassTheUblSchema(): void
    {
        $loader    = new UblXmlDocumentLoader();
        $validator = new UblSchemaValidator();
        $writer    = new UblDocumentWriter();

        $validator->validateDocument($loader->parse($writer->write($this->draft(UblDocumentRoot::INVOICE))));
        $validator->validateDocument($loader->parse($writer->write($this->creditNote())));

        $this->addToAssertionCount(1);
    }

    public function testWritesTopLevelAggregatesInUblSchemaOrder(): void
    {
        $document = new DOMDocument();
        $this->assertTrue($document->loadXML((new UblDocumentWriter())->write(
            $this->expandedDraft(UblDocumentRoot::INVOICE)
        )));

        $elementNames = [];

        foreach ($document->documentElement->childNodes as $child) {
            if ($child instanceof DOMElement) {
                $elementNames[] = $child->localName;
            }
        }

        $orderedAggregates = [
            'AccountingSupplierParty',
            'AccountingCustomerParty',
            'PayeeParty',
            'TaxRepresentativeParty',
            'Delivery',
            'PaymentMeans',
            'PaymentTerms',
            'AllowanceCharge',
            'TaxTotal',
            'LegalMonetaryTotal',
            'InvoiceLine',
        ];
        $positions = [];

        foreach ($orderedAggregates as $aggregate) {
            $position = array_search($aggregate, $elementNames, true);
            $this->assertNotFalse($position, sprintf('Expected top-level %s aggregate.', $aggregate));
            $positions[] = $position;
        }

        $sortedPositions = $positions;
        sort($sortedPositions);
        $this->assertSame($sortedPositions, $positions);
    }

    /**
     * Serializes payment-means variants as UBL.
     *
     * @dataProvider paymentMeansVariants
     * @param string $root    Document root element name.
     * @param string $code    Payment means code.
     * @param string $variant Variant label used by the data provider.
     */
    public function testWritesPaymentMeansVariants(
        string $root,
        string $code,
        string $variant
    ): void {
        $draft     = $root === UblDocumentRoot::CREDIT_NOTE ? $this->creditNote() : $this->draft($root);
        $accountId = $variant === 'account'
            ? new UblDraftIdentifier('SK0000000000000000000000')
            : null;
        $paymentMeans = new UblDraftPaymentMeans(
            PaymentMeansCode::fromString($code),
            new UblDraftIdentifier('PAYMENT-1'),
            $accountId
        );

        if ($variant === 'card') {
            $paymentMeans = $paymentMeans->withCardAccount(
                new UblDraftPaymentCard(
                    new UblDraftIdentifier('****1111'),
                    new UblDraftIdentifier('VISA'),
                    'Synthetic holder'
                )
            );
        } elseif ($variant === 'mandate') {
            $paymentMeans = $paymentMeans->withPaymentMandate(
                new UblDraftPaymentMandate(
                    new UblDraftIdentifier('MANDATE-1'),
                    new UblDraftIdentifier('SK1111111111111111111111')
                )
            );
        }

        $draft = $draft->withPayment(
            $draft->payment()
                    ->withPaymentMeans($paymentMeans)
        );
        $xml = (new UblDocumentWriter())->write($draft);

        $this->assertStringContainsString('<cbc:PaymentMeansCode>' . $code . '</cbc:PaymentMeansCode>', $xml);

        if ($variant === 'account') {
            $this->assertStringContainsString('<cac:PayeeFinancialAccount>', $xml);
        } elseif ($variant === 'card') {
            $this->assertStringContainsString('<cac:CardAccount>', $xml);
        } else {
            $this->assertStringContainsString('<cac:PaymentMandate>', $xml);
        }
    }

    /** @return array<string, array{string, string, string}> */
    public static function paymentMeansVariants(): array
    {
        return [
            'invoice_credit_transfer'          => [UblDocumentRoot::INVOICE, PaymentMeansCode::CREDIT_TRANSFER, 'account'],
            'credit_note_credit_transfer'      => [UblDocumentRoot::CREDIT_NOTE, PaymentMeansCode::CREDIT_TRANSFER, 'account'],
            'invoice_sepa_credit_transfer'     => [UblDocumentRoot::INVOICE, PaymentMeansCode::SEPA_CREDIT_TRANSFER, 'account'],
            'credit_note_sepa_credit_transfer' => [UblDocumentRoot::CREDIT_NOTE, PaymentMeansCode::SEPA_CREDIT_TRANSFER, 'account'],
            'invoice_payment_card'             => [UblDocumentRoot::INVOICE, PaymentMeansCode::PAYMENT_CARD, 'card'],
            'credit_note_payment_card'         => [UblDocumentRoot::CREDIT_NOTE, PaymentMeansCode::PAYMENT_CARD, 'card'],
            'invoice_sepa_direct_debit'        => [UblDocumentRoot::INVOICE, PaymentMeansCode::SEPA_DIRECT_DEBIT, 'mandate'],
            'credit_note_sepa_direct_debit'    => [UblDocumentRoot::CREDIT_NOTE, PaymentMeansCode::SEPA_DIRECT_DEBIT, 'mandate'],
        ];
    }

    /**
     * Serializes attachment variants as UBL.
     *
     * @dataProvider attachmentVariants
     * @param string $root    Document root element name.
     * @param string $variant Attachment variant label.
     */
    public function testWritesAttachmentVariants(string $root, string $variant): void
    {
        $draft     = $root === UblDocumentRoot::CREDIT_NOTE ? $this->creditNote() : $this->draft($root);
        $reference = (new UblDraftDocumentReference(
            UblDocumentReferenceType::fromString(UblDocumentReferenceType::ADDITIONAL),
            new UblDraftIdentifier('ATTACHMENT-1')
        ))
            ->withDescription('Supporting document');

        if ($variant === 'embedded') {
            $reference = $reference->withEmbeddedDocument(
                Base64DocumentContent::fromString('c3ludGhldGlj'),
                MimeType::fromString('application/pdf'),
                'support.pdf'
            );
        } else {
            $reference = $reference->withExternalUri(ExternalDocumentUri::fromString('https://example.test/support.pdf'));
        }

        $draft = $draft->withReferences(
            $draft->references()
                    ->withDocumentReference($reference)
        );
        $xml = (new UblDocumentWriter())->write($draft);
        $this->assertStringContainsString(
            $variant === 'embedded' ? '<cbc:EmbeddedDocumentBinaryObject' : '<cac:ExternalReference>',
            $xml
        );
    }

    /** @return array<string, array{string, string}> */
    public static function attachmentVariants(): array
    {
        $cases = [];

        foreach ([UblDocumentRoot::INVOICE, UblDocumentRoot::CREDIT_NOTE] as $root) {
            foreach (['embedded', 'external'] as $variant) {
                $cases[strtolower($root) . '_' . $variant] = [$root, $variant];
            }
        }

        return $cases;
    }

    public function testCreditNoteDueDateIsInThePaymentMeans(): void
    {
        $xml = (new UblDocumentWriter())->write($this->creditNote());

        $this->assertStringNotContainsString('<cbc:DueDate>', $xml);
        $this->assertStringContainsString('<cbc:PaymentDueDate>2026-12-10</cbc:PaymentDueDate>', $xml);
        $this->assertStringContainsString('<cbc:CreditNoteTypeCode>381</cbc:CreditNoteTypeCode>', $xml);
        $this->assertStringContainsString('<cac:CreditNoteLine>', $xml);
        $this->assertStringContainsString('<cbc:CreditedQuantity unitCode="C62">2.0000</cbc:CreditedQuantity>', $xml);
        $this->assertLessThan(strpos($xml, '<cbc:CreditNoteTypeCode>'), strpos($xml, '<cbc:TaxPointDate>'));
        $this->assertStringContainsString('<cbc:ID>ORIGINAL-1</cbc:ID>', $xml);
    }

    public function testWritesCanonicalUnitCodesProvidedByTheDraft(): void
    {
        $draft = $this->draft(UblDocumentRoot::INVOICE);
        $line  = $draft->lines()[0];
        $lines = [];

        foreach (['H87', 'PR'] as $index => $unitValue) {
            $lines[] = new UblDraftLine(
                new UblDraftIdentifier((string) ($index + 1)),
                $line->quantity(),
                UnitCode::fromCanonical($unitValue),
                $line->lineExtensionAmount(),
                $line->itemName(),
                $line->taxCategory(),
                $line->priceAmount()
            );
        }

        $draftWithCanonicalUnits = $draft->withLines($lines);
        $xml                     = (new UblDocumentWriter())->write($draftWithCanonicalUnits);

        $this->assertStringContainsString('unitCode="H87"', $xml);
        $this->assertStringContainsString('unitCode="PR"', $xml);
        $this->assertSame('H87', $draftWithCanonicalUnits->lines()[0]->unitCode()->value());
    }

    public function testExemptionReasonsAreWrittenOnlyInTheVatBreakdown(): void
    {
        $outOfScope = new UblDraftTaxCategory(
            TaxCategoryCode::fromString(TaxCategoryCode::OUT_OF_SCOPE),
            null,
            VatExemptionReasonCode::fromString(VatExemptionReasonCode::NOT_SUBJECT_TO_VAT)
        );
        $draft = $this->draft(UblDocumentRoot::INVOICE, $outOfScope);

        $xml = (new UblDocumentWriter())->write($draft);

        $this->assertSame(1, substr_count($xml, '<cbc:TaxExemptionReasonCode>VATEX-EU-O</cbc:TaxExemptionReasonCode>'));
        $this->assertStringNotContainsString('<cbc:Percent>', $xml);
    }

    public function testCreditNoteDueDateWithoutPaymentMeansIsRejected(): void
    {
        $draft = new UblDocumentDraft(
            UblDocumentRoot::fromString(UblDocumentRoot::CREDIT_NOTE),
            UblDocumentNumber::fromString('CN-1'),
            LocalDate::fromString('2026-11-10'),
            CurrencyCode::fromCanonical('EUR'),
            $this->party('0245:2012345676'),
            $this->party('0245:2012345687'),
            $this->totals(null)
        );

        $this->expectException(InvalidArgumentException::class);

        (new UblDocumentWriter())->write($draft->withDates(
            $draft->dates()
                    ->withDueDate(LocalDate::fromString('2026-12-10'))
        ));
    }

    public function testFormatsDecimals(): void
    {
        $this->assertSame('-0.05', UblDecimal::fromMinorUnits(-5)->value());
        $this->assertSame('123.00', UblDecimal::fromMinorUnits(12300)->value());
        $this->assertSame('12', UblDecimal::fromMinorUnits(12, 0)->value());
        $this->assertSame('2.5000', UblDecimal::fromFloat(2.5, 4)->value());
    }

    public function testOnlyInvoiceAndCreditNoteDraftsExist(): void
    {
        $this->expectException(InvalidArgumentException::class);

        UblDocumentRoot::fromString('Order');
    }

    public function testWritesOptionalDeliveryOrderReferenceAndRoundingAmount(): void
    {
        $category = new UblDraftTaxCategory(
            TaxCategoryCode::fromString(TaxCategoryCode::STANDARD),
            UblDecimal::fromString('23')
        );
        $totals = new UblDraftTotals(
            UblDecimal::fromString('100.00'),
            UblDecimal::fromString('100.00'),
            UblDecimal::fromString('123.00'),
            UblDecimal::fromString('0.01'),
            UblDecimal::fromString('123.01'),
            UblDecimal::fromString('23.00'),
            [new UblDraftTaxSubtotal(
                UblDecimal::fromString('100.00'),
                UblDecimal::fromString('23.00'),
                $category
            )]
        );
        $draft = (new UblDocumentDraft(
            UblDocumentRoot::fromString(UblDocumentRoot::INVOICE),
            UblDocumentNumber::fromString('INV-OPTIONAL'),
            LocalDate::fromString('2026-11-10'),
            CurrencyCode::fromCanonical('EUR'),
            $this->party('0245:2012345676'),
            $this->party('0245:2012345687'),
            $totals
        ))
            ->withDates(
                UblDraftDates::none()
                    ->withDeliveryDate(LocalDate::fromString('2026-11-12'))
            )
            ->withReferences(
                UblDraftReferences::none()
                    ->withOrderReference(new UblDraftIdentifier('ORDER-1'))
            );

        $xml = (new UblDocumentWriter())->write($draft);

        $this->assertStringContainsString('<cbc:ActualDeliveryDate>2026-11-12</cbc:ActualDeliveryDate>', $xml);
        $this->assertStringContainsString('<cac:OrderReference>', $xml);
        $this->assertStringContainsString('<cbc:ID>ORDER-1</cbc:ID>', $xml);
        $this->assertStringContainsString(
            '<cbc:PayableRoundingAmount currencyID="EUR">0.01</cbc:PayableRoundingAmount>',
            $xml
        );
    }

    public function testIdentifierObjectsRetainSchemesAcrossDraftAggregates(): void
    {
        $baseDraft = $this->draft(UblDocumentRoot::INVOICE);
        $supplier  = $baseDraft->supplier()->withTaxScheme(new UblDraftPartyTaxScheme(
            new UblDraftIdentifier('TAX-REG-1', 'TAX-SCHEME'),
            TaxSchemeCode::fromString(TaxSchemeCode::VAT)
        ));
        $draft = new UblDocumentDraft(
            $baseDraft->kind(),
            $baseDraft->number(),
            $baseDraft->issueDate(),
            $baseDraft->currency(),
            $supplier,
            $baseDraft->customer(),
            $baseDraft->totals()
        );
        $baseLine = $baseDraft->lines()[0];
        $line     = (new UblDraftLine(
            new UblDraftIdentifier('LINE-1', 'LINE-SCHEME'),
            $baseLine->quantity(),
            $baseLine->unitCode(),
            $baseLine->lineExtensionAmount(),
            $baseLine->itemName(),
            $baseLine->taxCategory(),
            $baseLine->priceAmount()
        ))->withOrderLineReference(new UblDraftIdentifier('ORDER-LINE-1', 'ORDER-LINE-SCHEME'));
        $accountPayment = (new UblDraftPaymentMeans(
            PaymentMeansCode::fromString(PaymentMeansCode::CREDIT_TRANSFER),
            new UblDraftIdentifier('PAYMENT-1', 'PAYMENT-SCHEME'),
            new UblDraftIdentifier('ACCOUNT-1', 'ACCOUNT-SCHEME')
        ))->withFinancialInstitutionBranchId(new UblDraftIdentifier('BRANCH-1', 'BRANCH-SCHEME'));
        $cardPayment = (new UblDraftPaymentMeans(PaymentMeansCode::fromString(PaymentMeansCode::PAYMENT_CARD)))
            ->withCardAccount(new UblDraftPaymentCard(
                new UblDraftIdentifier('****1111', 'PAN-SCHEME'),
                new UblDraftIdentifier('CARD-NETWORK', 'NETWORK-SCHEME')
            ));
        $mandatePayment = (new UblDraftPaymentMeans(
            PaymentMeansCode::fromString(PaymentMeansCode::SEPA_DIRECT_DEBIT)
        ))
            ->withPaymentMandate(new UblDraftPaymentMandate(
                new UblDraftIdentifier('MANDATE-1', 'MANDATE-SCHEME'),
                new UblDraftIdentifier('PAYER-ACCOUNT-1', 'PAYER-SCHEME')
            ));

        $draft = $draft
                ->withReferences(
                    UblDraftReferences::none()
                        ->withOrderReference(new UblDraftIdentifier('ORDER-1', 'ORDER-SCHEME'))
                        ->withSalesOrderReference(new UblDraftIdentifier('SALES-ORDER-1', 'SALES-ORDER-SCHEME'))
                        ->withDocumentReference(new UblDraftDocumentReference(
                            UblDocumentReferenceType::fromString(UblDocumentReferenceType::DESPATCH),
                            new UblDraftIdentifier('DESPATCH-1', 'REFERENCE-SCHEME')
                        ))
                )
                ->withTaxRepresentative(new UblDraftTaxRepresentative(
                    'Synthetic representative',
                    $supplier->address(),
                    new UblDraftIdentifier('REP-1', 'REPRESENTATIVE-SCHEME')
                ))
                ->withPayment(
                    UblDraftPayment::none()
                        ->withPaymentMeans($accountPayment)
                        ->withAdditionalPaymentMeans($cardPayment)
                        ->withAdditionalPaymentMeans($mandatePayment)
                )
                ->withLines([$line]);
        $xml = (new UblDocumentWriter())->write($draft);

        foreach (
            [
            '<cbc:ID schemeID="LINE-SCHEME">LINE-1</cbc:ID>',
            '<cbc:LineID schemeID="ORDER-LINE-SCHEME">ORDER-LINE-1</cbc:LineID>',
            '<cbc:ID schemeID="ORDER-SCHEME">ORDER-1</cbc:ID>',
            '<cbc:SalesOrderID schemeID="SALES-ORDER-SCHEME">SALES-ORDER-1</cbc:SalesOrderID>',
            '<cbc:ID schemeID="REFERENCE-SCHEME">DESPATCH-1</cbc:ID>',
            '<cbc:CompanyID schemeID="TAX-SCHEME">TAX-REG-1</cbc:CompanyID>',
            '<cbc:CompanyID schemeID="REPRESENTATIVE-SCHEME">REP-1</cbc:CompanyID>',
            '<cbc:PaymentID schemeID="PAYMENT-SCHEME">PAYMENT-1</cbc:PaymentID>',
            '<cbc:ID schemeID="ACCOUNT-SCHEME">ACCOUNT-1</cbc:ID>',
            '<cbc:ID schemeID="BRANCH-SCHEME">BRANCH-1</cbc:ID>',
            '<cbc:PrimaryAccountNumberID schemeID="PAN-SCHEME">****1111</cbc:PrimaryAccountNumberID>',
            '<cbc:NetworkID schemeID="NETWORK-SCHEME">CARD-NETWORK</cbc:NetworkID>',
            '<cbc:ID schemeID="MANDATE-SCHEME">MANDATE-1</cbc:ID>',
            '<cbc:ID schemeID="PAYER-SCHEME">PAYER-ACCOUNT-1</cbc:ID>',
            ] as $expectedIdentifier
        ) {
            $this->assertStringContainsString($expectedIdentifier, $xml);
        }
    }

    public function testWritesExpandedPeppolBillingComponentsForInvoiceAndCreditNote(): void
    {
        $writer          = new UblDocumentWriter();
        $schemaValidator = new UblSchemaValidator();
        $loader          = new UblXmlDocumentLoader();

        foreach ([UblDocumentRoot::INVOICE, UblDocumentRoot::CREDIT_NOTE] as $kind) {
            $draft = $this->expandedDraft($kind);
            $xml   = $writer->write($draft);
            try {
                $schemaValidator->validateDocument($loader->parse($xml));
            } catch (SchemaValidationException $exception) {
                $messages = array_map(static function ($issue): string {
                    return $issue->toArray()['message'];
                }, $exception->issues());

                preg_match_all(
                    '/<cac:(BillingReference|DespatchDocumentReference|ReceiptDocumentReference|OriginatorDocumentReference|ContractDocumentReference|AdditionalDocumentReference|ProjectReference)>/',
                    $xml,
                    $referenceMatches
                );
                self::fail(
                    $kind . ': ' . $exception->getMessage() . "\n"
                    . implode("\n", $messages) . "\n"
                    . implode(', ', $referenceMatches[1])
                );
            }

            $this->assertStringContainsString('<cbc:TaxCurrencyCode>USD</cbc:TaxCurrencyCode>', $xml);
            $this->assertStringContainsString('<cbc:AccountingCost>DEPT-42</cbc:AccountingCost>', $xml);
            $this->assertStringContainsString('<cbc:CompanyLegalForm>limited liability</cbc:CompanyLegalForm>', $xml);
            $this->assertStringContainsString('<cbc:Telephone>+421200000000</cbc:Telephone>', $xml);
            $this->assertStringContainsString('<cbc:ActualDeliveryDate>2026-11-12</cbc:ActualDeliveryDate>', $xml);
            $this->assertStringContainsString('<cbc:PrimaryAccountNumberID>****1111</cbc:PrimaryAccountNumberID>', $xml);
            $this->assertStringContainsString('<cac:PaymentMandate>', $xml);
            $this->assertStringContainsString('<cbc:PrepaidAmount currencyID="EUR">10.00</cbc:PrepaidAmount>', $xml);
            $this->assertStringContainsString('<cbc:EmbeddedDocumentBinaryObject', $xml);
            $this->assertStringContainsString('<cac:ExternalReference>', $xml);
            $this->assertStringContainsString('<cbc:ItemClassificationCode', $xml);
            $this->assertStringContainsString('<cbc:BaseQuantity unitCode="C62">1</cbc:BaseQuantity>', $xml);
            $this->assertStringContainsString('<cbc:AllowanceTotalAmount currencyID="EUR">5.00</cbc:AllowanceTotalAmount>', $xml);

            if ($kind === UblDocumentRoot::CREDIT_NOTE) {
                $this->assertStringContainsString('<cac:AdditionalDocumentReference>', $xml);
                $this->assertStringContainsString(
                    '<cbc:DocumentTypeCode>' . AdditionalDocumentTypeCode::PROJECT_REFERENCE . '</cbc:DocumentTypeCode>',
                    $xml
                );
                $this->assertStringContainsString('<cbc:PaymentDueDate>2026-12-10</cbc:PaymentDueDate>', $xml);
            } else {
                $this->assertStringContainsString('<cac:ProjectReference>', $xml);
                $this->assertStringContainsString('<cbc:DueDate>2026-12-10</cbc:DueDate>', $xml);
            }
        }
    }

    /** Returns an invoice draft converted to a credit note with a billing reference and tax point date. */
    private function creditNote(): UblDocumentDraft
    {
        $draft = $this->draft(UblDocumentRoot::CREDIT_NOTE);

        return $draft
            ->withTypeCode(UblDocumentTypeCode::fromString(DocumentTypeCode::CREDIT_NOTE))
            ->withDates($draft->dates()->withTaxPointDate(LocalDate::fromString('2026-11-10')))
            ->withReferences(
                $draft->references()
                    ->withBillingReference(UblDocumentNumber::fromString('ORIGINAL-1'), LocalDate::fromString('2026-10-20'))
            );
    }

    /** Builds a complete document draft with addresses, contacts, tax data, payment details, and references. */
    private function expandedDraft(string $kind): UblDocumentDraft
    {
        $category = new UblDraftTaxCategory(
            TaxCategoryCode::fromString(TaxCategoryCode::STANDARD),
            UblDecimal::fromString('23')
        );
        $address = (new UblDraftAddress(CountryCode::fromCanonical('SK')))
            ->withLines('Example 1', 'Building B', 'Floor 3')
            ->withLocality('Bratislava', '811 01', 'Bratislavský kraj');
        $supplier = (new UblDraftParty(
            UblEndpointIdentifier::fromSchemeAndValue('0245', '2012345676'),
            'Synthetic supplier s.r.o.',
            CountryCode::fromCanonical('SK')
        ))
            ->withAddress($address)
            ->withTradingName('Synthetic trade')
            ->withCompanyLegalForm('limited liability')
            ->withContact(new UblDraftContact('Billing', '+421200000000', EmailAddress::fromString('billing@example.test')))
            ->withTaxScheme(new UblDraftPartyTaxScheme(
                new UblDraftIdentifier('SK2012345676'),
                TaxSchemeCode::fromString(TaxSchemeCode::VAT)
            ));
        $customerAddress = (new UblDraftAddress(CountryCode::fromCanonical('SK')))
            ->withLines('Other 2')
            ->withLocality('Košice', '040 01');
        $customer = (new UblDraftParty(
            UblEndpointIdentifier::fromSchemeAndValue('0245', '2012345687'),
            'Synthetic customer a.s.',
            CountryCode::fromCanonical('SK')
        ))
            ->withAddress($customerAddress)
            ->withContact(new UblDraftContact('Accounts payable', null, EmailAddress::fromString('ap@example.test')));
        $line = (new UblDraftLine(
            new UblDraftIdentifier('1'),
            UblDecimal::fromString('2.0000'),
            UnitCode::fromCanonical('C62'),
            UblDecimal::fromString('95.00'),
            'Synthetic item',
            $category,
            UblDecimal::fromString('50.000000')
        ))
            ->withNote('Line note')
            ->withAccountingCost('LINE-42')
            ->withPeriod(new UblDraftPeriod(
                LocalDate::fromString('2026-11-01'),
                LocalDate::fromString('2026-11-30')
            ))
            ->withOrderLineReference(new UblDraftIdentifier('ORDER-LINE-1'))
            ->withDocumentReference(new UblDraftIdentifier('SUPPORT-1'))
            ->withAllowanceCharges([
                new UblDraftAllowanceCharge(
                    false,
                    UblDecimal::fromString('5.00'),
                    null,
                    AllowanceChargeReasonCode::fromString('95'),
                    'Discount',
                    UblDecimal::fromString('5'),
                    UblDecimal::fromString('100.00')
                ),
            ])
            ->withItemDescription('Detailed item description')
            ->withItemIdentifiers(
                new UblDraftIdentifier('BUYER-1'),
                new UblDraftIdentifier('SELLER-1'),
                new UblDraftIdentifier('1234567890123', '0160')
            )
            ->withOriginCountryCode(CountryCode::fromCanonical('SK'))
            ->withCommodityClassifications([new UblDraftCommodityClassification('12345678', 'HS')])
            ->withItemProperties([new UblDraftItemProperty('Color', 'Blue')])
            ->withPriceBaseQuantity(UblDecimal::fromString('1'), UnitCode::fromCanonical('C62'))
            ->withPriceAllowance(new UblDraftPriceAllowance(
                UblDecimal::fromString('5.00'),
                UblDecimal::fromString('55.00')
            ));

        $totals = (new UblDraftTotals(
            UblDecimal::fromString('95.00'),
            UblDecimal::fromString('92.00'),
            UblDecimal::fromString('113.16'),
            null,
            UblDecimal::fromString('103.16'),
            UblDecimal::fromString('21.16'),
            [new UblDraftTaxSubtotal(
                UblDecimal::fromString('92.00'),
                UblDecimal::fromString('21.16'),
                $category
            )]
        ))->withAdditionalAmounts(
            UblDecimal::fromString('5.00'),
            UblDecimal::fromString('2.00'),
            UblDecimal::fromString('10.00'),
            UblDecimal::fromString('21.16')
        );
        $accountPayment = (new UblDraftPaymentMeans(
            PaymentMeansCode::fromString(PaymentMeansCode::CREDIT_TRANSFER),
            new UblDraftIdentifier('VS-1'),
            new UblDraftIdentifier('SK0000000000000000000000'),
            'Synthetic bank account'
        ))
            ->withName('Credit transfer')
            ->withFinancialInstitutionBranchId(new UblDraftIdentifier('BANK-SK-1'));
        $cardPayment = (new UblDraftPaymentMeans(
            PaymentMeansCode::fromString(PaymentMeansCode::CREDIT_TRANSFER),
            new UblDraftIdentifier('VS-1'),
            new UblDraftIdentifier('SK0000000000000000000000')
        ))->withCardAccount(new UblDraftPaymentCard(
            new UblDraftIdentifier('****1111'),
            new UblDraftIdentifier('VISA'),
            'Synthetic holder'
        ));
        $mandatePayment = (new UblDraftPaymentMeans(
            PaymentMeansCode::fromString(PaymentMeansCode::CREDIT_TRANSFER),
            new UblDraftIdentifier('VS-1'),
            new UblDraftIdentifier('SK0000000000000000000000')
        ))->withPaymentMandate(new UblDraftPaymentMandate(
            new UblDraftIdentifier('MANDATE-1'),
            new UblDraftIdentifier('SK1111111111111111111111')
        ));
        $attachment = (new UblDraftDocumentReference(
            UblDocumentReferenceType::fromString(UblDocumentReferenceType::ADDITIONAL),
            new UblDraftIdentifier('ATTACHMENT-1')
        ))
            ->withDescription('Supporting document')
            ->withEmbeddedDocument(
                Base64DocumentContent::fromString('c3ludGhldGlj'),
                MimeType::fromString('application/pdf'),
                'support.pdf'
            );
        $remoteDocument = (new UblDraftDocumentReference(
            UblDocumentReferenceType::fromString(UblDocumentReferenceType::ADDITIONAL),
            new UblDraftIdentifier('REMOTE-1')
        ))->withExternalUri(ExternalDocumentUri::fromString('https://example.test/remote.pdf'));
        $invoicedObject = (new UblDraftDocumentReference(
            UblDocumentReferenceType::fromString(UblDocumentReferenceType::ADDITIONAL),
            new UblDraftIdentifier('OBJECT-1')
        ))->withDocumentType(AdditionalDocumentTypeCode::fromString(AdditionalDocumentTypeCode::INVOICED_OBJECT));
        $draft = (new UblDocumentDraft(
            UblDocumentRoot::fromString($kind),
            UblDocumentNumber::fromString('FULL-1'),
            LocalDate::fromString('2026-11-10'),
            CurrencyCode::fromCanonical('EUR'),
            $supplier,
            $customer,
            $totals
        ))
                ->withDates(
                    UblDraftDates::none()
                        ->withDueDate(LocalDate::fromString('2026-12-10'))
                        ->withTaxPointDate(LocalDate::fromString('2026-11-10'))
                        ->withInvoicePeriod(new UblDraftPeriod(
                            LocalDate::fromString('2026-11-01'),
                            LocalDate::fromString('2026-11-30')
                        ))
                )
                ->withReferences(
                    UblDraftReferences::none()
                        ->withAccountingCost('DEPT-42')
                        ->withBuyerReference('BUYER-REFERENCE')
                        ->withOrderReference(new UblDraftIdentifier('PURCHASE-ORDER-1'))
                        ->withSalesOrderReference(new UblDraftIdentifier('SALES-ORDER-1'))
                        ->withBillingReference(UblDocumentNumber::fromString('ORIGINAL-1'), LocalDate::fromString('2026-10-20'))
                        ->withDocumentReference(new UblDraftDocumentReference(
                            UblDocumentReferenceType::fromString(UblDocumentReferenceType::DESPATCH),
                            new UblDraftIdentifier('DESPATCH-1')
                        ))
                        ->withDocumentReference(new UblDraftDocumentReference(
                            UblDocumentReferenceType::fromString(UblDocumentReferenceType::RECEIPT),
                            new UblDraftIdentifier('RECEIPT-1')
                        ))
                        ->withDocumentReference(new UblDraftDocumentReference(
                            UblDocumentReferenceType::fromString(UblDocumentReferenceType::ORIGINATOR),
                            new UblDraftIdentifier('ORIGINATOR-1')
                        ))
                        ->withDocumentReference(new UblDraftDocumentReference(
                            UblDocumentReferenceType::fromString(UblDocumentReferenceType::CONTRACT),
                            new UblDraftIdentifier('CONTRACT-1')
                        ))
                        ->withDocumentReference($attachment)
                        ->withDocumentReference($remoteDocument)
                        ->withDocumentReference($invoicedObject)
                        ->withDocumentReference(new UblDraftDocumentReference(
                            UblDocumentReferenceType::fromString(UblDocumentReferenceType::PROJECT),
                            new UblDraftIdentifier('PROJECT-1')
                        ))
                )
                ->withTaxCurrencyCode(CurrencyCode::fromCanonical('USD'))
                ->withDelivery(new UblDraftDelivery(
                    LocalDate::fromString('2026-11-12'),
                    new UblDraftIdentifier('DELIVERY-LOCATION'),
                    $address,
                    'Receiving department'
                ))
                ->withPayment(
                    UblDraftPayment::none()
                        ->withPayee(new UblDraftPayee(
                            'Synthetic payee',
                            new UblDraftIdentifier('PAYEE-1'),
                            new UblDraftIdentifier('12345678')
                        ))
                        ->withPaymentMeans($accountPayment)
                        ->withAdditionalPaymentMeans($cardPayment)
                        ->withAdditionalPaymentMeans($mandatePayment)
                        ->withTermsNote('Pay according to the agreed terms.')
                )
                ->withTaxRepresentative(new UblDraftTaxRepresentative(
                    'Tax representative',
                    $address,
                    new UblDraftIdentifier('SK2020000000')
                ))
                ->withAllowanceCharges([
                new UblDraftAllowanceCharge(
                    false,
                    UblDecimal::fromString('5.00'),
                    $category,
                    AllowanceChargeReasonCode::fromString('95'),
                    'Discount',
                    UblDecimal::fromString('5'),
                    UblDecimal::fromString('100.00')
                ),
                new UblDraftAllowanceCharge(
                    true,
                    UblDecimal::fromString('2.00'),
                    $category,
                    AllowanceChargeReasonCode::fromString('AA'),
                    'Handling charge',
                    UblDecimal::fromString('2'),
                    UblDecimal::fromString('100.00')
                ),
            ])
                ->withLines([$line]);

        return $draft;
    }

    private function draft(string $kind, ?UblDraftTaxCategory $category = null): UblDocumentDraft
    {
        $category ??= new UblDraftTaxCategory(
            TaxCategoryCode::fromString(TaxCategoryCode::STANDARD),
            UblDecimal::fromString('23')
        );
        $customer = $this->party('0245:2012345687')
            ->withIdentification(new UblDraftIdentifier('2012345687', PartyIdentifierScheme::SK_DIC));
        $supplier = $this->party('0245:2012345676')
            ->withTaxScheme(new UblDraftPartyTaxScheme(
                new UblDraftIdentifier('SK2012345676'),
                TaxSchemeCode::fromString(TaxSchemeCode::VAT)
            ))
            ->withTaxScheme(new UblDraftPartyTaxScheme(
                new UblDraftIdentifier('2012345676'),
                TaxSchemeCode::fromString(TaxSchemeCode::OTHER)
            ))
            ->withLegalCompanyId(new UblDraftIdentifier('20000006'));
        $line = new UblDraftLine(
            new UblDraftIdentifier('1'),
            UblDecimal::fromFloat(2, 4),
            UnitCode::fromCanonical('C62'),
            UblDecimal::fromString('100.00'),
            'Synthetic item',
            $category,
            UblDecimal::fromString('50.000000')
        );

        return (new UblDocumentDraft(
            UblDocumentRoot::fromString($kind),
            UblDocumentNumber::fromString('INV-1'),
            LocalDate::fromString('2026-11-10'),
            CurrencyCode::fromCanonical('EUR'),
            $supplier,
            $customer,
            $this->totals($category)
        ))
                ->withDates(
                    UblDraftDates::none()
                        ->withDueDate(LocalDate::fromString('2026-12-10'))
                )
                ->withReferences(
                    UblDraftReferences::none()
                        ->withBuyerReference('SYNTHETIC-REFERENCE')
                )
                ->withPayment(
                    UblDraftPayment::none()
                        ->withPaymentMeans(new UblDraftPaymentMeans(
                            PaymentMeansCode::fromString(PaymentMeansCode::CREDIT_TRANSFER),
                            new UblDraftIdentifier('VS-1'),
                            new UblDraftIdentifier('SK0000000000000000000000')
                        ))
                )
                ->withLines([$line]);
    }

    private function party(string $endpoint): UblDraftParty
    {
        $address = (new UblDraftAddress(CountryCode::fromCanonical('SK')))
            ->withLines('Synthetic 1')
            ->withLocality('Testov', '00000');

        return (new UblDraftParty(
            UblEndpointIdentifier::fromSchemeAndValue(
                substr($endpoint, 0, strpos($endpoint, ':')),
                substr($endpoint, strpos($endpoint, ':') + 1)
            ),
            'Synthetic party',
            CountryCode::fromCanonical('SK')
        ))->withAddress($address);
    }

    private function totals(?UblDraftTaxCategory $category): UblDraftTotals
    {
        $outOfScope = $category !== null
            && $category->category()->value() === TaxCategoryCode::OUT_OF_SCOPE;
        $tax        = $outOfScope ? '0.00' : '23.00';
        $taxDecimal = UblDecimal::fromString($tax);
        $subtotals  = $category === null ? [] : [
            new UblDraftTaxSubtotal(UblDecimal::fromString('100.00'), $taxDecimal, $category),
        ];

        $total = $outOfScope ? '100.00' : '123.00';

        return new UblDraftTotals(
            UblDecimal::fromString('100.00'),
            UblDecimal::fromString('100.00'),
            UblDecimal::fromString($total),
            null,
            UblDecimal::fromString($total),
            $taxDecimal,
            $subtotals
        );
    }
}
