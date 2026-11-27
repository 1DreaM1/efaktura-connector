<?php

namespace DreaM1\EfakturaConnector\Infrastructure\Ubl\CodeList;

use DreaM1\EfakturaConnector\Core\CodeLists\CodeListIdentifiers;
use DreaM1\EfakturaConnector\Core\CodeLists\CodeListOption;
use DreaM1\EfakturaConnector\Core\Contracts\CodeListInterface;
use DreaM1\EfakturaConnector\Support\Exceptions\InvalidInputException;

/** Exposes one bundled Peppol Billing list as a code list provider. */
final class PeppolBillingCodeListProvider implements CodeListInterface
{
    private string $sourceIdentifier;

    /**
     * Creates a provider for one supported Peppol Billing list.
     *
     * @param  string                $sourceIdentifier Identifier from PeppolBillingCodeList.
     * @throws InvalidInputException when the source identifier is not supported.
     */
    public function __construct(string $sourceIdentifier)
    {
        if (! in_array($sourceIdentifier, PeppolBillingCodeList::identifiers(), true)) {
            throw new InvalidInputException('Unsupported Peppol Billing code list identifier.');
        }

        $this->sourceIdentifier = $sourceIdentifier;
    }

    /** {@inheritdoc} */
    public function identifier(): string
    {
        return CodeListIdentifiers::peppolBilling($this->sourceIdentifier);
    }

    /** @return CodeListOption[] */
    public function options(): array
    {
        $options = [];

        foreach (PeppolBillingCodeList::all($this->sourceIdentifier) as $code) {
            $name      = PeppolBillingCodeList::name($this->sourceIdentifier, $code);
            $options[] = new CodeListOption($code, $name ?? $code);
        }

        return $options;
    }
}
