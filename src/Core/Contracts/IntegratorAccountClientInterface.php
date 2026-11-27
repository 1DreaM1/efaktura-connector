<?php

namespace DreaM1\EfakturaConnector\Core\Contracts;

use DreaM1\EfakturaConnector\Core\Exceptions\ProviderApiException;
use DreaM1\EfakturaConnector\Core\Exceptions\RequestCoordinationException;
use DreaM1\EfakturaConnector\Core\Exceptions\RequestEncodingException;
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

/**
 * Manages OAuth clients, participant access and onboarding for one integrator account.
 * Intended for consumption by host applications; implementing it in a host class is not supported and new methods may be added in minor releases.
 */
interface IntegratorAccountClientInterface
{
    /**
     * Lists one cursor-paginated page of OAuth clients.
     *
     * @param  ListClientsRequest           $request Filters for the requested page.
     * @return IntegratorClientList         Client values and the optional continuation token.
     * @throws ProviderApiException         when the provider request fails or its response is invalid
     * @throws RequestCoordinationException when local HTTP request coordination cannot be established
     */
    public function listClients(ListClientsRequest $request): IntegratorClientList;

    /**
     * Creates an OAuth client and returns its one-time credentials.
     *
     * @param  CreateClientRequest          $request Client details and initial permissions.
     * @return IntegratorClientCreation     Client metadata and the one-time secret, when returned.
     * @throws ProviderApiException         when the provider request fails or its response is invalid
     * @throws RequestEncodingException     when the request body cannot be encoded
     * @throws RequestCoordinationException when local HTTP request coordination cannot be established
     */
    public function createClient(CreateClientRequest $request): IntegratorClientCreation;

    /**
     * Retrieves one OAuth client.
     *
     * @param  IntegratorClientReference    $request Identifier of the client to retrieve.
     * @return IntegratorClient             Client metadata returned by the provider.
     * @throws ProviderApiException         when the provider request fails or its response is invalid
     * @throws RequestCoordinationException when local HTTP request coordination cannot be established
     */
    public function getClient(IntegratorClientReference $request): IntegratorClient;

    /**
     * Deletes one OAuth client.
     *
     * @param  IntegratorClientReference    $request Identifier of the client to delete.
     * @throws ProviderApiException         when the provider request fails
     * @throws RequestCoordinationException when local HTTP request coordination cannot be established
     */
    public function deleteClient(IntegratorClientReference $request): void;

    /**
     * Replaces the OAuth client's complete participant access list.
     *
     * @param  ReplaceClientParticipantAccessRequest $request Client and replacement permission list.
     * @return IntegratorClient                      Updated client metadata returned by the provider.
     * @throws ProviderApiException                  when the provider request fails or its response is invalid
     * @throws RequestEncodingException              when the request body cannot be encoded
     * @throws RequestCoordinationException          when local HTTP request coordination cannot be established
     */
    public function replaceClientParticipantAccess(
        ReplaceClientParticipantAccessRequest $request
    ): IntegratorClient;

    /**
     * Rotates an OAuth client's secret.
     *
     * @param  IntegratorClientReference      $request Identifier of the client whose secret will be rotated.
     * @return IntegratorClientSecretRotation New one-time secret, when returned by the provider.
     * @throws ProviderApiException           when the provider request fails or its response is invalid
     * @throws RequestCoordinationException   when local HTTP request coordination cannot be established
     */
    public function rotateClientSecret(IntegratorClientReference $request): IntegratorClientSecretRotation;

    /**
     * Lists one cursor-paginated page of integrator participants.
     *
     * @param  ListParticipantsRequest      $request Filters for the requested page.
     * @return IntegratorParticipantList    Participant values and the optional continuation token.
     * @throws ProviderApiException         when the provider request fails or its response is invalid
     * @throws RequestCoordinationException when local HTTP request coordination cannot be established
     */
    public function listParticipants(ListParticipantsRequest $request): IntegratorParticipantList;

    /**
     * Returns participant document counts for a billing month in yyyyMM format.
     *
     * @param  GetDocumentSummaryRequest    $request Billing month to summarize.
     * @return IntegratorDocumentSummary    Monthly counts returned by the provider.
     * @throws ProviderApiException         when the provider request fails or its response is invalid
     * @throws RequestCoordinationException when local HTTP request coordination cannot be established
     */
    public function getDocumentSummary(GetDocumentSummaryRequest $request): IntegratorDocumentSummary;

    /**
     * Requests participant contract activation using an idempotency key.
     *
     * @param  ActivateParticipantRequest   $request Participant, mode and idempotency key for activation.
     * @return ParticipantActivation        Activation result returned by the provider.
     * @throws ProviderApiException         when the provider request fails or its response is invalid
     * @throws RequestEncodingException     when the request body cannot be encoded
     * @throws RequestCoordinationException when local HTTP request coordination cannot be established
     */
    public function requestParticipantActivation(ActivateParticipantRequest $request): ParticipantActivation;

    /**
     * Submits PDS participant subjects using the configured PDS signing secret.
     *
     * @param  SubmitPdsSubjectsRequest     $request Subjects and their signing secret.
     * @return PdsSubjectResponse           Provider acknowledgement.
     * @throws ProviderApiException         when the provider request fails or its response is invalid
     * @throws RequestEncodingException     when the request body cannot be encoded
     * @throws RequestCoordinationException when local HTTP request coordination cannot be established
     */
    public function submitPdsSubjects(SubmitPdsSubjectsRequest $request): PdsSubjectResponse;
}
