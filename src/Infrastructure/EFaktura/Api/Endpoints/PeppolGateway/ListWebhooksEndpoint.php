<?php

namespace DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Endpoints\PeppolGateway;

use DreaM1\EfakturaConnector\Core\Webhooks\ListWebhooksRequest;
use DreaM1\EfakturaConnector\Core\Webhooks\WebhookRegistryPage;
use DreaM1\EfakturaConnector\Domain\EFaktura\Participant;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\ApiRequest;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Endpoints\AbstractEndpoint;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Endpoints\PageQuery;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Response\PeppolGateway\WebhookRegistryPageFactory;

/**
 * Lists webhook registrations and returns the Core registry page.
 * GET /v1/webhooks?pageToken=&limit= → 200 WebhookList
 */
class ListWebhooksEndpoint extends AbstractEndpoint
{
    /**
     * Sends the request to the provider endpoint and returns its mapped response.
     *
     * @param  Participant         $participant Participant whose webhook page is requested.
     * @param  ListWebhooksRequest $request     Optional page cursor and page size.
     * @return WebhookRegistryPage
     */
    public function send(Participant $participant, ListWebhooksRequest $request): WebhookRegistryPage
    {
        return $this->api->json(
            ApiRequest::get(PeppolGatewayApiPaths::WEBHOOKS)
                ->withQuery(PageQuery::of($request->pageToken(), $request->limit()))
                ->forParticipant($participant),
            [WebhookRegistryPageFactory::class, 'fromArray']
        );
    }
}
