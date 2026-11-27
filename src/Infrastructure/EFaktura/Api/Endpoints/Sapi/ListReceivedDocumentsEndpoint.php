<?php

namespace DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Endpoints\Sapi;

use DreaM1\EfakturaConnector\Core\Documents\ListReceivedDocumentsRequest;
use DreaM1\EfakturaConnector\Core\Documents\ReceivedDocumentPage;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\ApiRequest;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Endpoints\AbstractEndpoint;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Endpoints\PageQuery;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Response\Sapi\ReceivedDocumentPageFactory;

/**
 * GET /sapi/document/receive?pageToken=&limit=&status= → 200 ReceivedDocumentPage
 *   { documents: DocumentMetadata[], nextPageToken? }
 */
class ListReceivedDocumentsEndpoint extends AbstractEndpoint
{
    /**
     * Sends the request to the provider endpoint and returns its mapped response.
     *
     * @param  ListReceivedDocumentsRequest $request Participant, optional document status and page parameters.
     * @return ReceivedDocumentPage
     */
    public function send(ListReceivedDocumentsRequest $request): ReceivedDocumentPage
    {
        return $this->api->json(
            ApiRequest::get(SapiApiPaths::RECEIVED_DOCUMENTS)
                ->withQuery(self::query($request))
                ->forParticipant($request->participant()),
            [ReceivedDocumentPageFactory::class, 'fromArray']
        );
    }

    /**
     * Maps typed paging filters to the SAPI query parameter names.
     *
     * @return array<string, string|int>
     */
    private static function query(ListReceivedDocumentsRequest $request): array
    {
        $query = PageQuery::of($request->pageToken(), $request->limit());

        if ($request->status() !== null) {
            $query['status'] = $request->status()->value();
        }

        return $query;
    }
}
