<?php

namespace DreaM1\EfakturaConnector\Domain\Ubl;

use DreaM1\EfakturaConnector\Support\Exceptions\InvalidInputException;

/**
 * Root element of a supported UBL document. It is the syntax, not the business meaning:
 * a debit note is an Invoice with type code 383 (CodeList\DocumentTypeCode::DEBIT_NOTE).
 */
final class UblDocumentRoot
{
    /** Supported UBL document root name 'Invoice'. */
    public const INVOICE = 'Invoice';
    /** Supported UBL document root name 'CreditNote'. */
    public const CREDIT_NOTE = 'CreditNote';

    private string $value;

    private function __construct(string $value)
    {
        $this->value = $value;
    }

    /**
     * Creates one of the UBL document roots supported by this package.
     *
     * @param  string                $rootName XML root element name.
     * @return self                  Supported UBL document root value object.
     * @throws InvalidInputException when the root name is not Invoice or CreditNote
     */
    public static function fromString(string $rootName): self
    {
        if (! self::isUblDocument($rootName)) {
            throw new InvalidInputException('A UBL Invoice or CreditNote root is required.');
        }

        return new self($rootName);
    }

    /** Returns the UBL root element name. */
    public function value(): string
    {
        return $this->value;
    }

    /**
     * Whether both objects represent the same UBL root.
     *
     * @param  self $other Root value to compare with this instance.
     * @return bool Whether the root names are equal.
     */
    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    /** Whether this root is CreditNote. */
    public function isCreditNote(): bool
    {
        return $this->value === self::CREDIT_NOTE;
    }

    /**
     * Whether the root element name is a supported UBL Invoice or CreditNote.
     *
     * @param  string $rootName Expected XML root element name.
     * @return bool
     */
    public static function isUblDocument(string $rootName): bool
    {
        return $rootName === self::INVOICE || $rootName === self::CREDIT_NOTE;
    }
}
