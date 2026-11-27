<?php

namespace DreaM1\EfakturaConnector\Domain\Ubl\Draft;

use DreaM1\EfakturaConnector\Support\Exceptions\InvalidInputException;
use DreaM1\EfakturaConnector\Support\StringValue;

/** Supported UBL relationship elements for document references. */
final class UblDocumentReferenceType
{
    use StringValue;

    /** UBL element name for a billing reference. */
    public const BILLING = 'BillingReference';
    /** UBL element name for a despatch document reference. */
    public const DESPATCH = 'DespatchDocumentReference';
    /** UBL element name for a receipt document reference. */
    public const RECEIPT = 'ReceiptDocumentReference';
    /** UBL element name for an originator document reference. */
    public const ORIGINATOR = 'OriginatorDocumentReference';
    /** UBL element name for a contract reference. */
    public const CONTRACT = 'ContractDocumentReference';
    /** UBL element name for an additional document reference. */
    public const ADDITIONAL = 'AdditionalDocumentReference';
    /** UBL element name for a project reference. */
    public const PROJECT = 'ProjectReference';

    /** @var string[] UBL relationship element names accepted by this value object. */
    private const ALLOWED_TYPES = [
        self::BILLING,
        self::DESPATCH,
        self::RECEIPT,
        self::ORIGINATOR,
        self::CONTRACT,
        self::ADDITIONAL,
        self::PROJECT,
    ];

    protected static function normalize(string $value): string
    {
        if (! in_array($value, self::ALLOWED_TYPES, true)) {
            throw new InvalidInputException('Unsupported Peppol Billing document reference type.');
        }

        return $value;
    }
}
