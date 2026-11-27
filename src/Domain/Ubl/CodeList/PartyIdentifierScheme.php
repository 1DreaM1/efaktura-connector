<?php

namespace DreaM1\EfakturaConnector\Domain\Ubl\CodeList;

/** ISO 6523 ICD schemes used by cac:PartyIdentification/cbc:ID, separate from UBL EndpointID schemes. */
final class PartyIdentifierScheme
{
    /** Scheme for a Slovak tax identification number (DIČ), recognized when present. */
    public const SK_DIC = '0245';
}
