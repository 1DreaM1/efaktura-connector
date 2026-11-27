<?php

namespace DreaM1\EfakturaConnector\Core\Webhooks;

use DreaM1\EfakturaConnector\Support\Exceptions\InvalidInputException;
use DreaM1\EfakturaConnector\Support\HttpsUrlValidator;

/** Immutable, validated callback URL value used by webhook models and requests. */
final class WebhookUrl
{
    private string $value;

    private function __construct(string $value)
    {
        $this->value = $value;
    }
    /**
     * Creates a callback URL value after validating that it is an absolute HTTPS URL.
     *
     * @param  string                $url Callback URL to validate.
     * @return self
     * @throws InvalidInputException when the URL is not an absolute HTTPS URL
     */
    public static function fromString(string $url): self
    {
        if (! HttpsUrlValidator::isAbsoluteHttpsUrl($url)) {
            throw new InvalidInputException('Webhook URL must be an absolute HTTPS URL.');
        }

        return new self($url);
    }

    /** Returns the original URL string. */
    public function value(): string
    {
        return $this->value;
    }
}
