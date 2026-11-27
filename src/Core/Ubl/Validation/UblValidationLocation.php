<?php

namespace DreaM1\EfakturaConnector\Core\Ubl\Validation;

/** Where a validation finding was reported in the document; each part is null when the stage did not provide it. */
final class UblValidationLocation
{
    private ?string $path;
    private ?int $line;
    private ?int $column;

    /**
     * Creates a finding location.
     *
     * @param string|null $path   XML location reported by the validator, such as a Schematron XPath.
     * @param int|null    $line   Source line number reported by the validation stage.
     * @param int|null    $column Column position reported by the validation stage.
     */
    public function __construct(?string $path = null, ?int $line = null, ?int $column = null)
    {
        $this->path   = $path;
        $this->line   = $line;
        $this->column = $column;
    }

    /** Returns the XML location, or null when unavailable. */
    public function path(): ?string
    {
        return $this->path;
    }

    /** Returns the source line number, or null when unavailable. */
    public function line(): ?int
    {
        return $this->line;
    }

    /** Returns the column position, or null when unavailable. */
    public function column(): ?int
    {
        return $this->column;
    }

    /**
     * Returns the location in the normalized serialization shape.
     *
     * @return array{path: string|null, line: int|null, column: int|null}
     */
    public function toArray(): array
    {
        return [
            'path'   => $this->path,
            'line'   => $this->line,
            'column' => $this->column,
        ];
    }
}
