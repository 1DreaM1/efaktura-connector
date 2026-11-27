<?php

namespace DreaM1\EfakturaConnector\Infrastructure\EFaktura\Connection\Facades;

use DreaM1\EfakturaConnector\Core\IntegratorApi\CreateClientRequest;
use DreaM1\EfakturaConnector\Core\IntegratorApi\IntegratorClient;
use DreaM1\EfakturaConnector\Core\IntegratorApi\IntegratorClientCreation;
use DreaM1\EfakturaConnector\Core\IntegratorApi\IntegratorClientList;
use DreaM1\EfakturaConnector\Core\IntegratorApi\IntegratorClientReference;
use DreaM1\EfakturaConnector\Core\IntegratorApi\IntegratorClientSecretRotation;
use DreaM1\EfakturaConnector\Core\IntegratorApi\IntegratorId;
use DreaM1\EfakturaConnector\Core\IntegratorApi\ListClientsRequest;
use DreaM1\EfakturaConnector\Core\IntegratorApi\ReplaceClientParticipantAccessRequest;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\ApiConnector;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Endpoints\Integrator\CreateClientEndpoint;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Endpoints\Integrator\DeleteClientEndpoint;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Endpoints\Integrator\GetClientEndpoint;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Endpoints\Integrator\ListClientsEndpoint;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Endpoints\Integrator\ReplaceClientParticipantAccessEndpoint;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Endpoints\Integrator\RotateClientSecretEndpoint;

/**
 * Groups the client management operations for one Integrator account.
 *
 * @internal
 */
final class IntegratorClientManagementFacade
{
    private IntegratorId $integratorId;
    private ListClientsEndpoint $clientList;
    private CreateClientEndpoint $clientCreate;
    private GetClientEndpoint $clientGet;
    private DeleteClientEndpoint $clientDelete;
    private ReplaceClientParticipantAccessEndpoint $clientParticipantAccess;
    private RotateClientSecretEndpoint $clientSecretRotate;

    /**
     * Creates client management operations scoped to one Integrator account.
     *
     * @param IntegratorId $integratorId Account identifier used for each endpoint request.
     * @param ApiConnector $api          Connector authenticated with the Integrator API token.
     */
    public function __construct(IntegratorId $integratorId, ApiConnector $api)
    {
        $this->integratorId            = $integratorId;
        $this->clientList              = new ListClientsEndpoint($api);
        $this->clientCreate            = new CreateClientEndpoint($api);
        $this->clientGet               = new GetClientEndpoint($api);
        $this->clientDelete            = new DeleteClientEndpoint($api);
        $this->clientParticipantAccess = new ReplaceClientParticipantAccessEndpoint($api);
        $this->clientSecretRotate      = new RotateClientSecretEndpoint($api);
    }

    /**
     * Lists clients matching the supplied filters and cursor.
     *
     * @param  ListClientsRequest   $request Filters and pagination settings.
     * @return IntegratorClientList The requested client page.
     */
    public function listClients(ListClientsRequest $request): IntegratorClientList
    {
        return $this->clientList->send($this->integratorId, $request);
    }

    /**
     * Creates a client in the configured Integrator account.
     *
     * @param  CreateClientRequest      $request Client details and idempotency key.
     * @return IntegratorClientCreation Created client and any one-time secret.
     */
    public function createClient(CreateClientRequest $request): IntegratorClientCreation
    {
        return $this->clientCreate->send($this->integratorId, $request);
    }

    /**
     * Retrieves a client and its participant access settings.
     *
     * @param  IntegratorClientReference $request Client identifier.
     * @return IntegratorClient          Requested client.
     */
    public function getClient(IntegratorClientReference $request): IntegratorClient
    {
        return $this->clientGet->send($this->integratorId, $request);
    }

    /**
     * Deletes a client from the configured Integrator account.
     *
     * @param IntegratorClientReference $request Client identifier.
     */
    public function deleteClient(IntegratorClientReference $request): void
    {
        $this->clientDelete->send($this->integratorId, $request);
    }

    /**
     * Replaces the participant access settings for a client.
     *
     * @param  ReplaceClientParticipantAccessRequest $request Complete replacement access list.
     * @return IntegratorClient                      Updated client.
     */
    public function replaceClientParticipantAccess(
        ReplaceClientParticipantAccessRequest $request
    ): IntegratorClient {
        return $this->clientParticipantAccess->send($this->integratorId, $request);
    }

    /**
     * Rotates a client's secret.
     *
     * @param  IntegratorClientReference      $request Client identifier.
     * @return IntegratorClientSecretRotation New one-time secret.
     */
    public function rotateClientSecret(IntegratorClientReference $request): IntegratorClientSecretRotation
    {
        return $this->clientSecretRotate->send($this->integratorId, $request);
    }
}
