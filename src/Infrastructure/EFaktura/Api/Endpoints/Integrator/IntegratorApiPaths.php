<?php

namespace DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Endpoints\Integrator;

/** Route templates declared by the Integrator API OpenAPI contract. */
final class IntegratorApiPaths
{
    /** Path for listing or creating integrator clients. */
    public const CLIENTS = '/v1/integrators/{integratorId}/clients';
    /** Path for retrieving or updating one integrator client. */
    public const CLIENT = '/v1/integrators/{integratorId}/clients/{clientId}';
    /** Path for rotating one integrator client's secret. */
    public const CLIENT_SECRET = '/v1/integrators/{integratorId}/clients/{clientId}/secret';
    /** Path for replacing one client's participant access list. */
    public const CLIENT_PARTICIPANTS = '/v1/integrators/{integratorId}/clients/{clientId}/participants';
    /** Path for listing participants accessible to an integrator. */
    public const PARTICIPANTS = '/v1/integrators/{integratorId}/participants';
    /** Path for forwarding PDS participant onboarding subjects. */
    public const PDS_SUBJECTS = '/v1/integrators/{integratorId}/pds/subjects';
    /** Path for retrieving an integrator document summary. */
    public const DOCUMENT_SUMMARY = '/v1/integrators/{integratorId}/document-summary';
    /** Path for listing or creating participant activations. */
    public const PARTICIPANT_ACTIVATIONS = '/v1/integrators/{integratorId}/participants/{participantId}/activations';
}
