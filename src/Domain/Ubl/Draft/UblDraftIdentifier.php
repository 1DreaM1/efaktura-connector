<?php

namespace DreaM1\EfakturaConnector\Domain\Ubl\Draft;

/** An identifier with an optional @schemeID, e.g. cac:PartyIdentification/cbc:ID or cbc:CompanyID. */
final class UblDraftIdentifier
{
    /** Identifier text written to the relevant UBL cbc element. */
    private string $value;
    /** Optional identifier scheme written as the element's schemeID attribute. */
    private ?string $scheme;

    /**
     * @param string      $value  Value to parse, normalize, or validate.
     * @param string|null $scheme Scheme identifier associated with the value.
     */
    public function __construct(string $value, ?string $scheme = null)
    {
        $this->value  = $value;
        $this->scheme = UblDraftText::emptyToNull($scheme);
    }

    /**
     * Returns the identifier text; the optional scheme is available separately through scheme().
     *
     * @return string
     */
    public function value(): string
    {
        return $this->value;
    }

    /**
     * Returns the scheme.
     *
     * @return string|null
     */
    public function scheme(): ?string
    {
        return $this->scheme;
    }
}
