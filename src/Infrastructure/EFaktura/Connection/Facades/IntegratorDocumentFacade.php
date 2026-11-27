<?php

namespace DreaM1\EfakturaConnector\Infrastructure\EFaktura\Connection\Facades;

use DreaM1\EfakturaConnector\Core\IntegratorApi\GetDocumentSummaryRequest;
use DreaM1\EfakturaConnector\Core\IntegratorApi\IntegratorDocumentSummary;
use DreaM1\EfakturaConnector\Core\IntegratorApi\IntegratorId;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\ApiConnector;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Endpoints\Integrator\GetDocumentSummaryEndpoint;

/**
 * Provides document summary operations for one Integrator account.
 *
 * @internal
 */
final class IntegratorDocumentFacade
{
    private IntegratorId $integratorId;
    private GetDocumentSummaryEndpoint $documentSummary;

    /**
     * Creates document summary operations scoped to one Integrator account.
     *
     * @param IntegratorId $integratorId Account identifier used for the endpoint request.
     * @param ApiConnector $api          Connector authenticated with the Integrator API token.
     */
    public function __construct(IntegratorId $integratorId, ApiConnector $api)
    {
        $this->integratorId    = $integratorId;
        $this->documentSummary = new GetDocumentSummaryEndpoint($api);
    }

    /**
     * Retrieves the document summary for the requested month.
     *
     * @param  GetDocumentSummaryRequest $request Month to summarize.
     * @return IntegratorDocumentSummary Provider summary for that month.
     */
    public function getDocumentSummary(GetDocumentSummaryRequest $request): IntegratorDocumentSummary
    {
        return $this->documentSummary->send($this->integratorId, $request);
    }
}
