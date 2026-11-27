<?php

namespace DreaM1\EfakturaConnector\Tests\Unit\Domain\Ubl\Draft;

use DreaM1\EfakturaConnector\Domain\Ubl\Draft\UblDraftIdentifier;
use DreaM1\EfakturaConnector\Domain\Ubl\Draft\UblDraftPaymentCard;
use DreaM1\EfakturaConnector\Support\Exceptions\InvalidInputException;
use PHPUnit\Framework\TestCase;

/** A card payment may carry only a masked or truncated account number, never a full card number. */
class UblDraftPaymentCardTest extends TestCase
{
    /**
     * @dataProvider safeAccountNumbers
     */
    public function testAcceptsMaskedAndTruncatedAccountNumbers(string $number): void
    {
        $card = new UblDraftPaymentCard(new UblDraftIdentifier($number), new UblDraftIdentifier('VISA'), 'Holder');

        $this->assertSame($number, $card->primaryAccountNumberId()->value());
        $this->assertSame('VISA', $card->networkId()->value());
        $this->assertSame('Holder', $card->holderName());
    }

    /** @return array<string, array{string}> */
    public static function safeAccountNumbers(): array
    {
        return [
            'last four digits'                                   => ['1111'],
            'masked'                                             => ['****1111'],
            'last six digits'                                    => ['421111'],
            'exactly ten characters'                             => ['*** 421111'],
            'inner whitespace is collapsed like normalize-space' => ['4111          1111'],
            'padded with spaces'                                 => ['   1111   '],
            'empty'                                              => [''],
        ];
    }

    /**
     * @dataProvider fullOrOversizedAccountNumbers
     */
    public function testRefusesAccountNumbersLongEnoughToBeAFullCardNumber(string $number): void
    {
        $this->expectException(InvalidInputException::class);
        $this->expectExceptionMessage('full card number must never be written to an invoice');

        new UblDraftPaymentCard(new UblDraftIdentifier($number), new UblDraftIdentifier('VISA'));
    }

    /** @return array<string, array{string}> */
    public static function fullOrOversizedAccountNumbers(): array
    {
        return [
            'sixteen digits'                   => ['4111111111111111'],
            'sixteen digits with spaces'       => ['4111 1111 1111 1111'],
            'sixteen digits with dashes'       => ['4111-1111-1111-1111'],
            'thirteen digits'                  => ['4222222222222'],
            'nineteen digits'                  => ['4111111111111111111'],
            'eleven characters'                => ['12345678901'],
            'eleven characters after trimming' => ['  12345678901  '],
            'malformed UTF-8 cannot evade it'  => ["\xC3\x28" . str_repeat('1', 16)],
        ];
    }

    public function testTheLimitIsTheSameAsPeppolRuleBr51(): void
    {
        $this->assertSame(10, UblDraftPaymentCard::MAX_PRIMARY_ACCOUNT_NUMBER_LENGTH);
    }
}
