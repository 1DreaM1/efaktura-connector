<?php

namespace DreaM1\EfakturaConnector\Domain\EFaktura\CodeList;

use DreaM1\EfakturaConnector\Support\Exceptions\InvalidInputException;

/** Current, registrable Peppol participant identifier schemes. */
final class ParticipantIdentifierSchemeCodeList
{
    /** OpenPeppol eDEC Participant Identifier Schemes release. */
    public const VERSION = '9.7';

    /** @var string[] */
    private const ACTIVE_REGISTRABLE_SCHEMES = [
        '0002', '0007', '0009', '0060', '0088', '0096', '0097', '0106', '0130', '0135',
        '0142', '0151', '0158', '0183', '0184', '0188', '0190', '0191', '0192', '0195',
        '0196', '0198', '0199', '0200', '0201', '0204', '0208', '0209', '0210',
        '0211', '0216', '0218', '0221', '0225', '0230', '0235', '0240', '0242', '0244',
        '0245', '0246', '0248', '9910', '9913', '9914', '9915', '9918', '9919', '9920',
        '9922', '9923', '9924', '9925', '9926', '9927', '9928', '9929', '9930', '9931',
        '9932', '9933', '9934', '9935', '9936', '9937', '9938', '9939', '9940', '9941',
        '9942', '9943', '9944', '9945', '9946', '9947', '9948', '9949', '9950', '9951',
        '9952', '9953', '9957', '9959',
    ];

    /**
     * Returns all active, registrable participant identifier schemes.
     *
     * @return string[]
     */
    public static function all(): array
    {
        return self::ACTIVE_REGISTRABLE_SCHEMES;
    }

    /**
     * Returns whether the scheme is active and registrable for a new document.
     *
     * @param  string $scheme Four-digit Peppol participant identifier scheme.
     * @return bool
     */
    public static function isAllowedForNewDocument(string $scheme): bool
    {
        return self::isCurrentAndRegistrable($scheme);
    }

    /**
     * Returns whether a scheme can be used for a participant-scoped Peppol API operation.
     *
     * @param  string $scheme Four-digit Peppol participant identifier scheme.
     * @return bool
     */
    public static function isAllowedForExistingOperation(string $scheme): bool
    {
        return self::isCurrentAndRegistrable($scheme);
    }

    /**
     * Ensures a scheme is current and registrable for a participant-scoped operation.
     *
     * @param  string                $scheme Four-digit Peppol participant identifier scheme.
     * @throws InvalidInputException when the scheme is unknown, deprecated or not registrable
     */
    public static function assertAllowedForExistingOperation(string $scheme): void
    {
        if (! self::isAllowedForExistingOperation($scheme)) {
            throw new InvalidInputException(
                'Participant scheme is deprecated, unknown, removed, or not registrable.'
            );
        }
    }

    private static function isCurrentAndRegistrable(string $scheme): bool
    {
        return in_array($scheme, self::ACTIVE_REGISTRABLE_SCHEMES, true);
    }
}
