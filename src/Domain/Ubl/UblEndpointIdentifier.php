<?php

namespace DreaM1\EfakturaConnector\Domain\Ubl;

/** A UBL EndpointID preserving its optional schemeID and value. */
final class UblEndpointIdentifier
{
    private ?string $scheme;
    private string $value;

    private function __construct(?string $scheme, string $value)
    {
        $this->scheme = $scheme;
        $this->value  = $value;
    }

    /**
     * Creates an endpoint identifier as represented in a UBL document.
     *
     * @param  string|null $scheme schemeID, or null when it is absent.
     * @param  string      $value  EndpointID text.
     * @return self
     */
    public static function fromSchemeAndValue(?string $scheme, string $value): self
    {
        return new self($scheme, $value);
    }

    /** Returns the UBL schemeID, or null when the source element had no schemeID. */
    public function scheme(): ?string
    {
        return $this->scheme;
    }

    /** Returns the EndpointID text without adding or removing a scheme prefix. */
    public function value(): string
    {
        return $this->value;
    }

    /**
     * Returns the scheme/value pair used by UBL model serialization.
     *
     * @return array{scheme: string|null, value: string}
     */
    public function toArray(): array
    {
        return [
            'scheme' => $this->scheme,
            'value'  => $this->value,
        ];
    }

    /** Returns a convenient serialized form; use scheme() and value() to preserve the UBL distinction. */
    public function identifier(): string
    {
        return $this->scheme === null ? $this->value : $this->scheme . ':' . $this->value;
    }
}
