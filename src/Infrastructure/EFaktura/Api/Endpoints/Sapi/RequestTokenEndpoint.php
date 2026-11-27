<?php

namespace DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Endpoints\Sapi;

use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\ApiConnector;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\ApiRequest;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Authentication\OAuthTokenRequest;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Endpoints\AbstractEndpoint;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Response\Sapi\SapiTokenResponse;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Transport\HttpTransportInterface;

/**
 * Obtains an access token with SAPI's OAuth client-credentials grant.
 *
 * POST /sapi/auth/token returns a SapiTokenResponse. This request is intentionally unauthenticated
 * and must use the plain transport: AuthenticatedRequestSender needs the resulting token to
 * authorize API calls. The request body follows SAPI's JSON contract and is marked safe to retry.
 */
class RequestTokenEndpoint extends AbstractEndpoint
{
    /**
     * @param HttpTransportInterface $transport HTTP transport used to perform the request.
     */
    public function __construct(HttpTransportInterface $transport)
    {
        parent::__construct(new ApiConnector($transport));
    }

    /**
     * Sends the request to the provider endpoint and returns its mapped response.
     *
     * @param  OAuthTokenRequest $request OAuth client credentials and optional scope for token acquisition.
     * @return SapiTokenResponse
     */
    public function send(OAuthTokenRequest $request): SapiTokenResponse
    {
        return $this->api->json(
            ApiRequest::post(SapiApiPaths::TOKEN)
                ->withJsonBody($request->toBody())
                ->retrySafe(),
            [SapiTokenResponse::class, 'fromArray']
        );
    }
}
