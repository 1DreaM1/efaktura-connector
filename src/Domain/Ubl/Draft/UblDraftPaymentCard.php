<?php

namespace DreaM1\EfakturaConnector\Domain\Ubl\Draft;

use DreaM1\EfakturaConnector\Support\Exceptions\InvalidInputException;

/**
 * Payment card details. The primary account number must be a safe representation (its last 4 to 6 digits, masked
 * or tokenized), never a full card number: such a number would be written to the invoice and sent to the buyer.
 */
final class UblDraftPaymentCard
{
    /** Longest primary account number representation accepted; Peppol rule BR-51 uses the same limit. */
    public const MAX_PRIMARY_ACCOUNT_NUMBER_LENGTH = 10;

    /** Masked/tokenized card number written to cac:CardAccount/cbc:PrimaryAccountNumberID. */
    private UblDraftIdentifier $primaryAccountNumberId;
    /** Card brand/network written to cac:CardAccount/cbc:NetworkID. */
    private UblDraftIdentifier $networkId;
    /** Optional cardholder name written to cac:CardAccount/cbc:HolderName. */
    private ?string $holderName;

    /**
     * Creates card payment details; provide a safe PAN representation, never the full card number.
     *
     * @param  UblDraftIdentifier    $primaryAccountNumberId Masked or tokenized primary account number identifier of at
     *                                                       most MAX_PRIMARY_ACCOUNT_NUMBER_LENGTH characters.
     * @param  UblDraftIdentifier    $networkId              Card network identifier.
     * @param  string|null           $holderName             Name printed on the card, when supplied.
     * @throws InvalidInputException when the account number is too long to be a masked or truncated value.
     */
    public function __construct(
        UblDraftIdentifier $primaryAccountNumberId,
        UblDraftIdentifier $networkId,
        ?string $holderName = null
    ) {
        // Same measure as BR-51 (normalize-space), but enforced: that rule is only a warning in local validation.
        $number     = trim($primaryAccountNumberId->value());
        $normalized = preg_replace('/\s+/u', ' ', $number);
        // preg_replace() fails on malformed UTF-8; count its bytes then, so such a value cannot evade the limit.
        $length = $normalized === null ? strlen($number) : mb_strlen($normalized, 'UTF-8');

        if ($length > self::MAX_PRIMARY_ACCOUNT_NUMBER_LENGTH) {
            throw new InvalidInputException(sprintf(
                'The card primary account number must be a masked or truncated value of at most %d characters; '
                . 'a full card number must never be written to an invoice.',
                self::MAX_PRIMARY_ACCOUNT_NUMBER_LENGTH
            ));
        }

        $this->primaryAccountNumberId = $primaryAccountNumberId;
        $this->networkId              = $networkId;
        $this->holderName             = UblDraftText::emptyToNull($holderName);
    }

    /** Returns the masked or tokenized primary account number identifier. */
    public function primaryAccountNumberId(): UblDraftIdentifier
    {
        return $this->primaryAccountNumberId;
    }

    /** Returns the card network identifier. */
    public function networkId(): UblDraftIdentifier
    {
        return $this->networkId;
    }

    /** Returns the cardholder name, or null when it is omitted. */
    public function holderName(): ?string
    {
        return $this->holderName;
    }
}
