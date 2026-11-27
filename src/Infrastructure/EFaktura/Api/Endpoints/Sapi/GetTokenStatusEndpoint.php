<?php

namespace DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Endpoints\Sapi;

use DreaM1\EfakturaConnector\Core\Connection\Health\TokenStatus;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\ApiRequest;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Endpoints\AbstractEndpoint;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Response\Sapi\TokenStatusFactory;

/**
 * GET /sapi/auth/token/status → 200 SapiTokenStatusResponse, mapped to TokenStatus
 */
class GetTokenStatusEndpoint extends AbstractEndpoint
{
    /**
     * Sends the request to the provider endpoint and returns its mapped response.
     *
     * @return TokenStatus
     */
    public function send(): TokenStatus
    {
        return $this->api->json(
            ApiRequest::get(SapiApiPaths::TOKEN_STATUS),
            [TokenStatusFactory::class, 'fromArray']
        );
    }
}
