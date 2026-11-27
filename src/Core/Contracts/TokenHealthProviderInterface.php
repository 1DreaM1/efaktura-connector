<?php

namespace DreaM1\EfakturaConnector\Core\Contracts;

use DreaM1\EfakturaConnector\Core\Connection\Health\TokenStatus;
use DreaM1\EfakturaConnector\Core\Exceptions\ProviderApiException;
use DreaM1\EfakturaConnector\Core\Exceptions\RequestCoordinationException;

/** Reads token status for the configured provider credentials. */
interface TokenHealthProviderInterface
{
    /**
     * Returns the status and refresh guidance for the currently authenticated token.
     *
     * When the provider rejects that token (HTTP 401) the status has valid = false and no other details, and the
     * rejected token is forgotten locally. The check does not replace the token and replay the call, so it never
     * reports the status of a different token than the one the client held.
     *
     * @return TokenStatus
     * @throws ProviderApiException         when the provider status cannot be retrieved
     * @throws RequestCoordinationException when local request
     *                                      coordination cannot be established
     */
    public function getTokenStatus(): TokenStatus;

    /**
     * Requests token status without making an application document call; false when the provider rejects the token.
     *
     * @return bool
     * @throws ProviderApiException         when the request fails or the token status is invalid
     * @throws RequestCoordinationException when local request
     *                                      coordination cannot be established
     */
    public function isTokenValid(): bool;
}
