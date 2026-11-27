<?php

namespace DreaM1\EfakturaConnector\Domain\Ubl\Draft;

/** Item name/value pair in cac:AdditionalItemProperty. */
final class UblDraftItemProperty
{
    /** Property label written to cac:AdditionalItemProperty/cbc:Name. */
    private string $name;
    /** Property value written to cac:AdditionalItemProperty/cbc:Value. */
    private string $value;

    /**
     * Creates an item property name and value written to cac:AdditionalItemProperty.
     *
     * @param string $name  Property name.
     * @param string $value Property value.
     */
    public function __construct(string $name, string $value)
    {
        $this->name  = $name;
        $this->value = $value;
    }

    /** Returns the property name. */
    public function name(): string
    {
        return $this->name;
    }

    /** Returns the property value. */
    public function value(): string
    {
        return $this->value;
    }
}
