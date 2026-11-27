<?php

namespace DreaM1\EfakturaConnector\Core\MessageStatuses;

/** Provider code and human-readable explanation for a message-level status. */
final class ProviderMessageLevelStatusReason
{
    private ?string $lineId;
    private ?string $code;
    private ?string $description;

    /** Restricts construction to factories that normalize provider reason fields. */
    private function __construct(?string $lineId, ?string $code, ?string $description)
    {
        $this->lineId      = $lineId;
        $this->code        = $code;
        $this->description = $description;
    }

    /**
     * Creates a message-level status reason from its provider fields.
     *
     * @param  string|null $lineId      Identifier of the affected billing document line.
     * @param  string|null $code        Code value to validate, map, or report.
     * @param  string|null $description Human-readable description associated with the value.
     * @return self
     */
    public static function create(?string $lineId, ?string $code, ?string $description): self
    {
        return new self($lineId, $code, $description);
    }

    /**
     * Returns the line ID.
     *
     * @return string|null
     */
    public function lineId(): ?string
    {
        return $this->lineId;
    }

    /**
     * Returns the code.
     *
     * @return string|null
     */
    public function code(): ?string
    {
        return $this->code;
    }

    /**
     * Returns the description.
     *
     * @return string|null
     */
    public function description(): ?string
    {
        return $this->description;
    }
}
