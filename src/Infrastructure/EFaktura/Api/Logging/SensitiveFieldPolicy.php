<?php

namespace DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Logging;

/** Shared vocabulary for credential fields found in structured and free-form log values. @internal */
final class SensitiveFieldPolicy
{
    /** @var string[] */
    private const FIELD_NAME_FRAGMENTS = [
        'password',
        'credential',
        'secret',
        'token',
        'privatekey',
        'apikey',
        // Integrator API requests include participant and business contact details.
        'participantid',
        'email',
        'phone',
        'name',
        'userid',
        'dic',
    ];

    /** @return string[] */
    public static function fieldNameFragments(): array
    {
        return self::FIELD_NAME_FRAGMENTS;
    }

    /** Returns credential field alternatives for the text redactor's key matcher. */
    public static function textFieldNamePattern(): string
    {
        $alternatives = [];

        foreach (self::FIELD_NAME_FRAGMENTS as $fragment) {
            $alternatives[] = $fragment === 'privatekey'
                ? 'private[_-]?key'
                : ($fragment === 'apikey' ? 'api[_-]?key' : preg_quote($fragment, '/'));
        }

        // A separated or concatenated prefix covers common names such as access_token and clientsecret.
        return '(?:[A-Za-z0-9]+[_-]?)?(?:' . implode('|', $alternatives) . ')';
    }
}
