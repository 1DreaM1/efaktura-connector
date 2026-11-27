<?php

namespace DreaM1\EfakturaConnector\Tests\Unit\Infrastructure\Ubl;

use DreaM1\EfakturaConnector\Core\CodeLists\CodeListIdentifiers;
use DreaM1\EfakturaConnector\Core\CodeLists\CodeListOption;
use DreaM1\EfakturaConnector\Core\Contracts\CodeListInterface;
use DreaM1\EfakturaConnector\Core\Contracts\CodeListRegistryInterface;
use DreaM1\EfakturaConnector\Infrastructure\Ubl\CodeList\CodeListRegistry;
use DreaM1\EfakturaConnector\Infrastructure\Ubl\CodeList\PeppolBillingCodeList;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/** Tests the default and custom code-list registry behavior. */
class CodeListRegistryTest extends TestCase
{
    public function testDefaultRegistryProvidesEveryBundledCodeList(): void
    {
        $expectedIdentifiers = [
            CodeListIdentifiers::COUNTRIES,
            CodeListIdentifiers::CURRENCIES,
            CodeListIdentifiers::UNITS,
            CodeListIdentifiers::ELECTRONIC_ADDRESS_SCHEMES,
            CodeListIdentifiers::PARTICIPANT_IDENTIFIER_SCHEMES,
            CodeListIdentifiers::UBL_DOCUMENT_TYPES,
            CodeListIdentifiers::ADDITIONAL_DOCUMENT_TYPES,
            CodeListIdentifiers::TAX_SCHEMES,
            CodeListIdentifiers::PEPPOL_BILLING_ICD,
            CodeListIdentifiers::PEPPOL_BILLING_VAT_CATEGORY,
            CodeListIdentifiers::PEPPOL_BILLING_ITEM_TYPE,
            CodeListIdentifiers::PEPPOL_BILLING_INVOICE_TYPE,
            CodeListIdentifiers::PEPPOL_BILLING_CREDIT_NOTE_TYPE,
            CodeListIdentifiers::PEPPOL_BILLING_INVOICED_OBJECT_SCHEME,
            CodeListIdentifiers::PEPPOL_BILLING_VAT_DATE_CODE,
            CodeListIdentifiers::PEPPOL_BILLING_PAYMENT_MEANS,
            CodeListIdentifiers::PEPPOL_BILLING_ALLOWANCE_REASON,
            CodeListIdentifiers::PEPPOL_BILLING_CHARGE_REASON,
            CodeListIdentifiers::PEPPOL_BILLING_MIME,
            CodeListIdentifiers::PEPPOL_BILLING_SEPA,
            CodeListIdentifiers::PEPPOL_BILLING_VATEX,
        ];
        $registry = new CodeListRegistry();

        $this->assertInstanceOf(CodeListRegistryInterface::class, $registry);
        $this->assertSame($expectedIdentifiers, $registry->identifiers());

        foreach ($expectedIdentifiers as $identifier) {
            $codeList = $registry->get($identifier);
            $options  = $codeList->options();

            $this->assertInstanceOf(CodeListInterface::class, $codeList);
            $this->assertSame($identifier, $codeList->identifier());
            $this->assertNotEmpty($options, sprintf('Code list "%s" has no options.', $identifier));

            foreach ($options as $option) {
                $this->assertInstanceOf(CodeListOption::class, $option);
                $this->assertNotSame('', $option->value());
                $this->assertNotSame('', trim($option->label()));
            }
        }
    }

    public function testReturnsUnitAndPeppolSchemeOptionsAsTypedValues(): void
    {
        $registry = new CodeListRegistry();
        $units    = $this->findOption($registry->get(CodeListIdentifiers::UNITS)->options(), 'H87');
        $eas      = $this->findOption(
            $registry->get(CodeListIdentifiers::ELECTRONIC_ADDRESS_SCHEMES)->options(),
            '0088'
        );
        $participants = $this->findOption(
            $registry->get(CodeListIdentifiers::PARTICIPANT_IDENTIFIER_SCHEMES)->options(),
            '0088'
        );
        $icd = $this->findOption(
            $registry->get(CodeListIdentifiers::PEPPOL_BILLING_ICD)->options(),
            '0245'
        );

        $this->assertStringContainsString('piece', $units->label());
        $this->assertSame('0088', $eas->label());
        $this->assertSame('0088', $participants->label());
        $this->assertSame(
            PeppolBillingCodeList::name(PeppolBillingCodeList::ICD, '0245'),
            $icd->label()
        );
    }

    public function testCanRegisterCustomCodeLists(): void
    {
        $customList = $this->createCodeList('custom', [new CodeListOption('custom-code', 'Custom label')]);
        $registry   = new CodeListRegistry([$customList]);

        $this->assertSame(['custom'], $registry->identifiers());
        $this->assertSame($customList, $registry->get('custom'));
        $this->assertSame('custom-code', $registry->get('custom')->options()[0]->value());
    }

    public function testRejectsDuplicateIdentifiers(): void
    {
        $first  = $this->createCodeList('duplicate', [new CodeListOption('A', 'A')]);
        $second = $this->createCodeList('duplicate', [new CodeListOption('B', 'B')]);

        $this->expectException(InvalidArgumentException::class);

        new CodeListRegistry([$first, $second]);
    }

    public function testRejectsUnknownIdentifiers(): void
    {
        $registry = new CodeListRegistry();

        $this->expectException(InvalidArgumentException::class);

        $registry->get('unknown');
    }

    /** @param CodeListOption[] $options */
    private function createCodeList(string $identifier, array $options): CodeListInterface
    {
        return new class ($identifier, $options) implements CodeListInterface {
            private string $identifier;
            /** @var CodeListOption[] */
            private array $options;

            /** @param CodeListOption[] $options */
            public function __construct(string $identifier, array $options)
            {
                $this->identifier = $identifier;
                $this->options    = $options;
            }

            /** {@inheritdoc} */
            public function identifier(): string
            {
                return $this->identifier;
            }

            /** @return CodeListOption[] */
            public function options(): array
            {
                return $this->options;
            }
        };
    }

    /** @param CodeListOption[] $options */
    private function findOption(array $options, string $value): CodeListOption
    {
        foreach ($options as $option) {
            if ($option->value() === $value) {
                return $option;
            }
        }

        $this->fail(sprintf('Code list option "%s" was not found.', $value));
    }
}
