<?php

namespace DreaM1\EfakturaConnector\Core\IntegratorApi;

use DreaM1\EfakturaConnector\Support\Exceptions\InvalidInputException;
use DreaM1\EfakturaConnector\Support\NonBlankString;

/** Contract modes supported for participant activation. */
final class ParticipantContractMode
{
    /** Participant contract mode that permits sending documents only. */
    public const SEND_ONLY = 'SEND_ONLY';
    /** Participant contract mode that permits sending and receiving documents. */
    public const SEND_AND_RECEIVE = 'SEND_AND_RECEIVE';

    /** @var string[] */
    public const SUPPORTED_MODES = [self::SEND_ONLY, self::SEND_AND_RECEIVE];

    private string $value;

    private function __construct(string $value)
    {
        $this->value = $value;
    }

    /**
     * Creates a contract mode declared by the Integrator API.
     *
     * @throws InvalidInputException when the mode is not supported.
     */
    public static function fromString(string $mode): self
    {
        if (! self::isSupported($mode)) {
            throw new InvalidInputException('Participant contract mode is not supported.');
        }

        return new self($mode);
    }

    /**
     * Preserves a non-blank mode returned by the provider, including values not known by this version.
     *
     * @throws InvalidInputException when the provider mode is blank
     */
    public static function fromProvider(string $mode): self
    {
        if (! NonBlankString::isNotBlank($mode)) {
            throw new InvalidInputException('Participant contract mode must not be empty.');
        }

        return new self($mode);
    }

    /** Returns the provider contract mode string. */
    public function value(): string
    {
        return $this->value;
    }

    /** Whether this contract mode is part of the vocabulary known by this version. */
    public function isKnown(): bool
    {
        return self::isSupported($this->value);
    }

    /** Returns whether the mode is declared by the API contract. */
    public static function isSupported(string $mode): bool
    {
        return in_array($mode, self::SUPPORTED_MODES, true);
    }

    /**
     * Returns the contract mode values accepted by the provider.
     *
     * @return string[]
     */
    public static function values(): array
    {
        return self::SUPPORTED_MODES;
    }
}
