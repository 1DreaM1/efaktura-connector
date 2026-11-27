<?php

namespace DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Endpoints;

use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\ApiConnector;

/** Shared base of provider endpoint classes: holds the API connector their request definitions run through. */
abstract class AbstractEndpoint
{
    protected ApiConnector $api;

    /**
     * Creates an endpoint bound to one API connector.
     *
     * @param ApiConnector $api API request definition used to build the provider call.
     */
    public function __construct(ApiConnector $api)
    {
        $this->api = $api;
    }
}
