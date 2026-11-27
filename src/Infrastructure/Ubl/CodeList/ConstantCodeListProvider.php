<?php

namespace DreaM1\EfakturaConnector\Infrastructure\Ubl\CodeList;

use DreaM1\EfakturaConnector\Core\CodeLists\CodeListOption;
use DreaM1\EfakturaConnector\Core\Contracts\CodeListInterface;
use DreaM1\EfakturaConnector\Support\Exceptions\InvalidInputException;
use ReflectionClass;

/** Exposes public string constants from a domain code type as selectable options. */
final class ConstantCodeListProvider implements CodeListInterface
{
    private string $identifier;
    /** @var class-string */
    private string $className;

    /**
     * Creates a provider for a class whose public string constants form one code list.
     *
     * @param  string                $identifier Stable identifier for this code list.
     * @param  string                $className  Class that declares code constants.
     * @throws InvalidInputException when either argument is invalid.
     */
    public function __construct(string $identifier, string $className)
    {
        if (trim($identifier) === '' || ! class_exists($className)) {
            throw new InvalidInputException('Code list identifier and class must be valid.');
        }

        $this->identifier = $identifier;
        $this->className  = $className;
    }

    /** {@inheritdoc} */
    public function identifier(): string
    {
        return $this->identifier;
    }

    /** @return CodeListOption[] */
    public function options(): array
    {
        $options = [];

        foreach ((new ReflectionClass($this->className))->getConstants() as $name => $value) {
            if (! is_string($value)) {
                continue;
            }

            $label     = ucwords(strtolower(str_replace('_', ' ', $name)));
            $label     = str_replace('Vat', 'VAT', $label);
            $options[] = new CodeListOption($value, $label);
        }

        return $options;
    }
}
