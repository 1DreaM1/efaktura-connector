<?php

namespace DreaM1\EfakturaConnector\Core\Exceptions;

/**
 * The OAuth token could not be obtained, so the API request itself was not sent.
 *
 * The failed token call is the previous exception (e.g. ProviderErrorResponseException with the provider's answer).
 * Local RequestDeferredException values are propagated directly and are not wrapped here.
 */
final class TokenRequestException extends ProviderApiException
{
    /**
     * @param ProviderApiException|null $previous Previous exception that caused this failure, when available.
     */
    public function __construct(?ProviderApiException $previous = null)
    {
        $statusCode      = null;
        $responseHeaders = [];

        if ($previous !== null) {
            $statusCode      = $previous->rawStatusCode();
            $responseHeaders = $previous->responseHeaders();
        }

        parent::__construct(
            'OAuth token request failed.',
            $statusCode,
            $responseHeaders,
            $previous
        );
    }

    /** The API request waited for the token, so it was never sent. */
    public function requestMayHaveReachedProvider(): bool
    {
        return false;
    }
}
