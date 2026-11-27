<?php

namespace DreaM1\EfakturaConnector\Core\Webhooks;

use DreaM1\EfakturaConnector\Support\Exceptions\InvalidInputException;
use DreaM1\EfakturaConnector\Support\NonBlankString;
use DreaM1\EfakturaConnector\Support\RefusesSerialization;

/** Sensitive secret used to verify webhook signatures. */
final class WebhookSigningSecret
{
    use RefusesSerialization;

    private string $secret;

    private function __construct(string $secret)
    {
        NonBlankString::assertNotBlank($secret, 'Webhook signing secret must not be blank.');

        $this->secret = $secret;
    }

    /**
     * Redacts the secret from var_dump() output.
     *
     * @return array{secret: string} Debug representation containing the redaction marker.
     */
    public function __debugInfo(): array
    {
        return ['secret' => '[redacted]'];
    }

    /**
     * Creates a secret from its provider-supplied representation.
     *
     * @param  string                $secret Non-blank signing secret.
     * @return self
     * @throws InvalidInputException when the secret is blank.
     */
    public static function fromString(string $secret): self
    {
        return new self($secret);
    }

    /** Returns the secret for explicit use in webhook signature verification. */
    public function reveal(): string
    {
        return $this->secret;
    }
}
