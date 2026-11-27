<?php

namespace DreaM1\EfakturaConnector\Infrastructure\EFaktura\Connection;

use DreaM1\EfakturaConnector\Core\Configuration\IntegratorAccountConfiguration;
use DreaM1\EfakturaConnector\Core\Contracts\IntegratorAccountClientInterface;
use DreaM1\EfakturaConnector\Core\IntegratorApi\ActivateParticipantRequest;
use DreaM1\EfakturaConnector\Core\IntegratorApi\CreateClientRequest;
use DreaM1\EfakturaConnector\Core\IntegratorApi\GetDocumentSummaryRequest;
use DreaM1\EfakturaConnector\Core\IntegratorApi\IntegratorClient;
use DreaM1\EfakturaConnector\Core\IntegratorApi\IntegratorClientCreation;
use DreaM1\EfakturaConnector\Core\IntegratorApi\IntegratorClientList;
use DreaM1\EfakturaConnector\Core\IntegratorApi\IntegratorClientReference;
use DreaM1\EfakturaConnector\Core\IntegratorApi\IntegratorClientSecretRotation;
use DreaM1\EfakturaConnector\Core\IntegratorApi\IntegratorDocumentSummary;
use DreaM1\EfakturaConnector\Core\IntegratorApi\IntegratorParticipantList;
use DreaM1\EfakturaConnector\Core\IntegratorApi\ListClientsRequest;
use DreaM1\EfakturaConnector\Core\IntegratorApi\ListParticipantsRequest;
use DreaM1\EfakturaConnector\Core\IntegratorApi\ParticipantActivation;
use DreaM1\EfakturaConnector\Core\IntegratorApi\PdsSubjectResponse;
use DreaM1\EfakturaConnector\Core\IntegratorApi\ReplaceClientParticipantAccessRequest;
use DreaM1\EfakturaConnector\Core\IntegratorApi\SubmitPdsSubjectsRequest;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\ApiConnector;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Connection\Facades\IntegratorClientManagementFacade;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Connection\Facades\IntegratorDocumentFacade;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Connection\Facades\IntegratorParticipantManagementFacade;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Connection\Facades\IntegratorPdsFacade;

/** Application-facing Integrator API implementation for one account. */
final class IntegratorAccountClient implements IntegratorAccountClientInterface
{
    private IntegratorClientManagementFacade $clients;
    private IntegratorParticipantManagementFacade $participants;
    private IntegratorDocumentFacade $documents;
    private IntegratorPdsFacade $pds;

    /**
     * Creates an Integrator API client with its typed operation groups.
     *
     * @param IntegratorAccountConfiguration $configuration Integrator account the client speaks for.
     * @param ApiConnector                   $tokenApi      Connector authenticated by the Integrator token.
     * @param ApiConnector                   $pdsApi        Connector that signs PDS submissions.
     */
    public function __construct(
        IntegratorAccountConfiguration $configuration,
        ApiConnector $tokenApi,
        ApiConnector $pdsApi
    ) {
        $integratorId = $configuration->integratorId();

        $this->clients      = new IntegratorClientManagementFacade($integratorId, $tokenApi);
        $this->participants = new IntegratorParticipantManagementFacade($integratorId, $tokenApi);
        $this->documents    = new IntegratorDocumentFacade($integratorId, $tokenApi);
        $this->pds          = new IntegratorPdsFacade($integratorId, $pdsApi);
    }

    /** {@inheritdoc} */
    public function listClients(ListClientsRequest $request): IntegratorClientList
    {
        return $this->clients->listClients($request);
    }

    /** {@inheritdoc} */
    public function createClient(CreateClientRequest $request): IntegratorClientCreation
    {
        return $this->clients->createClient($request);
    }

    /** {@inheritdoc} */
    public function getClient(IntegratorClientReference $request): IntegratorClient
    {
        return $this->clients->getClient($request);
    }

    /** {@inheritdoc} */
    public function deleteClient(IntegratorClientReference $request): void
    {
        $this->clients->deleteClient($request);
    }

    /** {@inheritdoc} */
    public function replaceClientParticipantAccess(
        ReplaceClientParticipantAccessRequest $request
    ): IntegratorClient {
        return $this->clients->replaceClientParticipantAccess($request);
    }

    /** {@inheritdoc} */
    public function rotateClientSecret(IntegratorClientReference $request): IntegratorClientSecretRotation
    {
        return $this->clients->rotateClientSecret($request);
    }

    /** {@inheritdoc} */
    public function listParticipants(ListParticipantsRequest $request): IntegratorParticipantList
    {
        return $this->participants->listParticipants($request);
    }

    /** {@inheritdoc} */
    public function getDocumentSummary(
        GetDocumentSummaryRequest $request
    ): IntegratorDocumentSummary {
        return $this->documents->getDocumentSummary($request);
    }

    /** {@inheritdoc} */
    public function requestParticipantActivation(ActivateParticipantRequest $request): ParticipantActivation
    {
        return $this->participants->requestParticipantActivation($request);
    }

    /** {@inheritdoc} */
    public function submitPdsSubjects(SubmitPdsSubjectsRequest $request): PdsSubjectResponse
    {
        return $this->pds->submitPdsSubjects($request);
    }
}
