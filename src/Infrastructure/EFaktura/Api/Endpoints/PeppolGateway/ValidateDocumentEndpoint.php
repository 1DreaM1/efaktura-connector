<?php

namespace DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Endpoints\PeppolGateway;

use DreaM1\EfakturaConnector\Core\Documents\ValidateDocumentRequest;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\ApiRequest;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Endpoints\AbstractEndpoint;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Response\PeppolGateway\DocumentValidationVerdictReader;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Serialization\DocumentSubmissionRequestMapper;

/**
 * POST /v1/validate-document (body SapiSendDocumentRequest) → 200 ValidateDocumentResponse, read as the provider verdict
 *
 * Validation has no side effects, so the transport may replay the call.
 */
class ValidateDocumentEndpoint extends AbstractEndpoint
{
    /**
     * Sends the request to the provider endpoint and returns its mapped response.
     *
     * @param  ValidateDocumentRequest $request Participant and outbound document payload to validate.
     * @return bool                    True when the provider accepted the document.
     */
    public function send(ValidateDocumentRequest $request): bool
    {
        return $this->api->json(
            ApiRequest::post(PeppolGatewayApiPaths::VALIDATE_DOCUMENT)
                ->withJsonBody(DocumentSubmissionRequestMapper::fromPayload($request->payload()))
                ->forParticipant($request->participant())
                ->retrySafe(),
            [DocumentValidationVerdictReader::class, 'fromArray']
        );
    }
}
