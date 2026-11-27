<?php

namespace DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Endpoints\Integrator;

use DreaM1\EfakturaConnector\Core\IntegratorApi\GetDocumentSummaryRequest;
use DreaM1\EfakturaConnector\Core\IntegratorApi\IntegratorDocumentSummary;
use DreaM1\EfakturaConnector\Core\IntegratorApi\IntegratorId;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\ApiRequest;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Endpoints\AbstractEndpoint;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Response\Integrator\IntegratorDocumentSummaryFactory;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Serialization\IntegratorRequestMapper;

/** GET /v1/integrators/{integratorId}/document-summary?month={yyyyMM} */
class GetDocumentSummaryEndpoint extends AbstractEndpoint
{
    /**
     * Retrieves the document counts for one billing month.
     *
     * @param  IntegratorId              $integratorId Provider-assigned integrator identifier.
     * @param  GetDocumentSummaryRequest $request      Billing month in yyyyMM format.
     * @return IntegratorDocumentSummary Monthly counts returned by the provider.
     */
    public function send(IntegratorId $integratorId, GetDocumentSummaryRequest $request): IntegratorDocumentSummary
    {
        return $this->api->json(
            ApiRequest::get(IntegratorApiPaths::DOCUMENT_SUMMARY, ['integratorId' => $integratorId->value()])
                ->withQuery(IntegratorRequestMapper::documentSummaryQuery($request)),
            [IntegratorDocumentSummaryFactory::class, 'fromArray']
        );
    }
}
