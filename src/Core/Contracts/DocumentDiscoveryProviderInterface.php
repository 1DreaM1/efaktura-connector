<?php

namespace DreaM1\EfakturaConnector\Core\Contracts;

use DreaM1\EfakturaConnector\Core\Connection\CheckReceivingCapabilityRequest;
use DreaM1\EfakturaConnector\Core\Connection\CheckReceivingCapabilityResponse;
use DreaM1\EfakturaConnector\Core\Exceptions\ProviderApiException;
use DreaM1\EfakturaConnector\Core\Exceptions\RequestCoordinationException;
use DreaM1\EfakturaConnector\Core\Exceptions\RequestEncodingException;

/** Checks whether a Peppol participant can receive a document and process. */
interface DocumentDiscoveryProviderInterface
{
    /**
     * Sends a discovery query for the requested participant, document type and process.
     *
     * @param  CheckReceivingCapabilityRequest  $request Participant, document type and process to check.
     * @return CheckReceivingCapabilityResponse
     * @throws ProviderApiException             when the request fails or the provider response is invalid
     * @throws RequestEncodingException         when the discovery request body cannot be encoded
     * @throws RequestCoordinationException     when local request
     *                                          coordination cannot be established
     */
    public function receives(CheckReceivingCapabilityRequest $request): CheckReceivingCapabilityResponse;
}
