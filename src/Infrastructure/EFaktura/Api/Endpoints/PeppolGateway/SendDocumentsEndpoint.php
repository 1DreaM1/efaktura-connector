<?php

namespace DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Endpoints\PeppolGateway;

use DreaM1\EfakturaConnector\Core\Configuration\HttpRequestBodyLimit;
use DreaM1\EfakturaConnector\Core\Documents\SendDocumentsRequest;
use DreaM1\EfakturaConnector\Core\Documents\SendDocumentsResult;
use DreaM1\EfakturaConnector\Core\Exceptions\InvalidProviderResponseException;
use DreaM1\EfakturaConnector\Core\Exceptions\RequestBodyTooLargeException;
use DreaM1\EfakturaConnector\Core\Exceptions\RequestEncodingException;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\ApiConnector;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\ApiRequest;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Endpoints\AbstractEndpoint;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Response\PeppolGateway\SendDocumentsResultFactory;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Serialization\SendDocumentsRequestMapper;

/** POST /v1/send-documents → 200 SendDocumentsResponse, mapped to SendDocumentsResult. */
class SendDocumentsEndpoint extends AbstractEndpoint
{
    /** Default maximum serialized batch request size, in bytes. */
    public const DEFAULT_MAX_REQUEST_BODY_BYTES = HttpRequestBodyLimit::DEFAULT_BYTES;

    private HttpRequestBodyLimit $requestBodyLimit;

    /**
     * @param ApiConnector              $api              API request definition used to build the provider call.
     * @param HttpRequestBodyLimit|null $requestBodyLimit Maximum serialized JSON body size, or the default limit.
     */
    public function __construct(
        ApiConnector $api,
        ?HttpRequestBodyLimit $requestBodyLimit = null
    ) {
        parent::__construct($api);

        $this->requestBodyLimit = $requestBodyLimit ?? HttpRequestBodyLimit::default();
    }

    /**
     * Sends the batch to the provider and returns its mapped response.
     *
     * @param  SendDocumentsRequest             $request Batch, participant and idempotency key to submit.
     * @return SendDocumentsResult
     * @throws InvalidProviderResponseException when the provider response is inconsistent with the submitted batch
     * @throws RequestBodyTooLargeException     when the encoded batch exceeds the configured size limit
     * @throws RequestEncodingException         when the batch cannot be encoded as JSON
     */
    public function send(SendDocumentsRequest $request): SendDocumentsResult
    {
        // Keep the idempotency key on the request, but never replay document submission automatically.
        $body = SendDocumentsRequestMapper::encode($request, $this->requestBodyLimit->bytes());

        return $this->api->json(
            ApiRequest::post(PeppolGatewayApiPaths::SEND_DOCUMENTS)
                ->withIdempotencyKey($request->idempotencyKey())
                ->withEncodedJsonBody($body)
                ->forParticipant($request->participant()),
            static function (array $data) use ($request): SendDocumentsResult {
                $result = SendDocumentsResultFactory::fromArray($data);

                if ($result->count() > count($request->documents())) {
                    throw new InvalidProviderResponseException(
                        'Provider response SendDocumentsResponse reports more accepted documents than were submitted.',
                        'SendDocumentsResponse',
                        'count'
                    );
                }

                return $result;
            }
        );
    }
}
