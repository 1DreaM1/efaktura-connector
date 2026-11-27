<?php

namespace DreaM1\EfakturaConnector\Infrastructure\EFaktura\Connection;

use DreaM1\EfakturaConnector\Core\Connection\CheckReceivingCapabilityRequest;
use DreaM1\EfakturaConnector\Core\Connection\CheckReceivingCapabilityResponse;
use DreaM1\EfakturaConnector\Core\Contracts\DocumentDiscoveryProviderInterface;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Endpoints\PeppolGateway\CheckReceivingCapabilityEndpoint;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Response\ParticipantResponseValidator;

/** Adapts Peppol Gateway discovery endpoint responses to the provider contract. */
class PeppolGatewayDiscoveryProvider implements DocumentDiscoveryProviderInterface
{
    /** Diagnostic message used when the discovery response has an invalid participant. */
    private const INVALID_RESULT_MESSAGE = 'Gateway returned an invalid discovery result.';

    private CheckReceivingCapabilityEndpoint $discovery;

    /**
     * @param CheckReceivingCapabilityEndpoint $discovery Provider discovery service used to check recipient support.
     */
    public function __construct(CheckReceivingCapabilityEndpoint $discovery)
    {
        $this->discovery = $discovery;
    }

    /**
     * Checks whether the participant accepts the requested document type and process.
     *
     * @param  CheckReceivingCapabilityRequest  $request Participant, document type and optional process to
     *                                                   check for receiving capability.
     * @return CheckReceivingCapabilityResponse
     *                                          {@inheritdoc}
     */
    public function receives(CheckReceivingCapabilityRequest $request): CheckReceivingCapabilityResponse
    {
        $response = $this->discovery->send($request);

        ParticipantResponseValidator::assertMatches(
            $request->participant(),
            $response->participant,
            self::INVALID_RESULT_MESSAGE
        );

        return $response;
    }
}
