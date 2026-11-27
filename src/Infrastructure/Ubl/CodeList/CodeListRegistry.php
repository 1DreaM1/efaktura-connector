<?php

namespace DreaM1\EfakturaConnector\Infrastructure\Ubl\CodeList;

use DreaM1\EfakturaConnector\Core\Contracts\CodeListInterface;
use DreaM1\EfakturaConnector\Core\Contracts\CodeListRegistryInterface;
use DreaM1\EfakturaConnector\Support\Exceptions\InvalidInputException;

/** Indexes code list providers and supplies the package defaults when none are passed. */
final class CodeListRegistry implements CodeListRegistryInterface
{
    /** @var array<string, CodeListInterface> */
    private array $codeLists = [];

    /**
     * Creates a registry from the supplied providers or all bundled providers by default.
     *
     * @param  iterable<CodeListInterface>|null $codeLists Custom or extended provider set.
     * @throws InvalidInputException            when a provider is invalid or identifiers are blank or duplicated.
     */
    public function __construct(?iterable $codeLists = null)
    {
        foreach ($codeLists ?? BundledCodeListFactory::create() as $codeList) {
            if (! $codeList instanceof CodeListInterface) {
                throw new InvalidInputException('Every registered code list must implement CodeListInterface.');
            }

            $identifier = $codeList->identifier();

            if (trim($identifier) === '') {
                throw new InvalidInputException('Code list identifier must not be empty.');
            }

            $key = self::storageKey($identifier);

            if (isset($this->codeLists[$key])) {
                throw new InvalidInputException('Code list identifiers must be unique.');
            }

            $this->codeLists[$key] = $codeList;
        }
    }

    /** {@inheritdoc} */
    public function get(string $identifier): CodeListInterface
    {
        $key = self::storageKey($identifier);

        if (! isset($this->codeLists[$key])) {
            throw new InvalidInputException('Unknown code list identifier: ' . $identifier . '.');
        }

        return $this->codeLists[$key];
    }

    /** @return string[] */
    public function identifiers(): array
    {
        $identifiers = [];

        foreach ($this->codeLists as $codeList) {
            $identifiers[] = $codeList->identifier();
        }

        return $identifiers;
    }

    private static function storageKey(string $identifier): string
    {
        return 'code-list:' . $identifier;
    }
}
