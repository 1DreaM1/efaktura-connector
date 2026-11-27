<?php

namespace DreaM1\EfakturaConnector\Core\CodeLists;

use DreaM1\EfakturaConnector\Support\Exceptions\InvalidInputException;

/** One selectable value and its display label from a code list. */
final class CodeListOption
{
    private string $value;
    private string $label;

    /**
     * Creates a code list option.
     *
     * @param  string                $value The canonical value submitted by the host application.
     * @param  string                $label The display text for the value.
     * @throws InvalidInputException when the value or label is blank.
     */
    public function __construct(string $value, string $label)
    {
        if (trim($value) === '') {
            throw new InvalidInputException('Code list option value must not be empty.');
        }

        if (trim($label) === '') {
            throw new InvalidInputException('Code list option label must not be empty.');
        }

        $this->value = $value;
        $this->label = $label;
    }

    /** Returns the exact code to persist or submit. */
    public function value(): string
    {
        return $this->value;
    }

    /** Returns the display label without changing the code value. */
    public function label(): string
    {
        return $this->label;
    }
}
