<?php

namespace DreaM1\EfakturaConnector\Infrastructure\EFaktura\Connection\Facades;

use DreaM1\EfakturaConnector\Core\IntegratorApi\IntegratorId;
use DreaM1\EfakturaConnector\Core\IntegratorApi\PdsSubjectResponse;
use DreaM1\EfakturaConnector\Core\IntegratorApi\SubmitPdsSubjectsRequest;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\ApiConnector;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Endpoints\Integrator\SubmitPdsSubjectsEndpoint;

/**
 * Provides PDS subject operations authenticated with the PDS signing key.
 *
 * @internal
 */
final class IntegratorPdsFacade
{
    private IntegratorId $integratorId;
    private SubmitPdsSubjectsEndpoint $subjects;

    /**
     * Creates PDS subject operations scoped to one Integrator account.
     *
     * @param IntegratorId $integratorId Account identifier used for the endpoint request.
     * @param ApiConnector $api          Connector using PDS signature authentication.
     */
    public function __construct(IntegratorId $integratorId, ApiConnector $api)
    {
        $this->integratorId = $integratorId;
        $this->subjects     = new SubmitPdsSubjectsEndpoint($api);
    }

    /**
     * Submits a batch of PDS subjects.
     *
     * @param  SubmitPdsSubjectsRequest $request Batch and signing secret.
     * @return PdsSubjectResponse       Provider response for the submitted subjects.
     */
    public function submitPdsSubjects(SubmitPdsSubjectsRequest $request): PdsSubjectResponse
    {
        return $this->subjects->send($this->integratorId, $request);
    }
}
