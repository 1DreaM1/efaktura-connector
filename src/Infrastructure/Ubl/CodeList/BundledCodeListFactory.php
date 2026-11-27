<?php

namespace DreaM1\EfakturaConnector\Infrastructure\Ubl\CodeList;

use DreaM1\EfakturaConnector\Core\CodeLists\CodeListIdentifiers;
use DreaM1\EfakturaConnector\Core\Contracts\CodeListInterface;
use DreaM1\EfakturaConnector\Domain\Ubl\CodeList\AdditionalDocumentTypeCode;
use DreaM1\EfakturaConnector\Domain\Ubl\CodeList\DocumentTypeCode;
use DreaM1\EfakturaConnector\Domain\Ubl\CodeList\TaxSchemeCode;

/** Creates the default providers backed by the package's bundled code-list sources. */
final class BundledCodeListFactory
{
    /**
     * Creates all built-in code list providers in their public registration order.
     *
     * @return CodeListInterface[]
     */
    public static function create(): array
    {
        $providers = [
            new CountryCodeListProvider(),
            new CurrencyCodeListProvider(),
            new UnitCodeListProvider(),
            new ElectronicAddressSchemeCodeListProvider(),
            new ParticipantIdentifierSchemeCodeListProvider(),
            new ConstantCodeListProvider(CodeListIdentifiers::UBL_DOCUMENT_TYPES, DocumentTypeCode::class),
            new ConstantCodeListProvider(
                CodeListIdentifiers::ADDITIONAL_DOCUMENT_TYPES,
                AdditionalDocumentTypeCode::class
            ),
            new ConstantCodeListProvider(CodeListIdentifiers::TAX_SCHEMES, TaxSchemeCode::class),
        ];

        foreach (PeppolBillingCodeList::identifiers() as $identifier) {
            $providers[] = new PeppolBillingCodeListProvider($identifier);
        }

        return $providers;
    }
}
