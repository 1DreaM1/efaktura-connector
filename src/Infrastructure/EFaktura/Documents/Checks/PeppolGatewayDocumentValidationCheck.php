<?php

namespace DreaM1\EfakturaConnector\Infrastructure\EFaktura\Documents\Checks;

use DreaM1\EfakturaConnector\Core\Contracts\ProviderDocumentValidationCheckInterface;
use DreaM1\EfakturaConnector\Core\Documents\ValidateDocumentRequest;
use DreaM1\EfakturaConnector\Core\Exceptions\DocumentRejectedByProviderException;
use DreaM1\EfakturaConnector\Core\Exceptions\InvalidProviderResponseException;
use DreaM1\EfakturaConnector\Core\Exceptions\ProviderApiException;
use DreaM1\EfakturaConnector\Core\Exceptions\RequestCoordinationException;
use DreaM1\EfakturaConnector\Core\Exceptions\RequestEncodingException;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Endpoints\PeppolGateway\ValidateDocumentEndpoint;

/**
 * Calls POST /v1/validate-document to check the payload without submitting it.
 *
 * A successful response with valid=false becomes DocumentRejectedByProviderException. An HTTP error
 * remains a ProviderApiException so the host can distinguish a rejected document from a failed check.
 */
final class PeppolGatewayDocumentValidationCheck implements ProviderDocumentValidationCheckInterface
{
    private ValidateDocumentEndpoint $validation;

    /**
     * @param ValidateDocumentEndpoint $validation Validation result or service used by the operation.
     */
    public function __construct(ValidateDocumentEndpoint $validation)
    {
        $this->validation = $validation;
    }

    /**
     * Completes the provider-side validation and throws when the payload is invalid.
     *
     * @param  ValidateDocumentRequest             $request Participant and document payload to validate.
     * @return void
     * @throws DocumentRejectedByProviderException the provider rejected the document
     * @throws ProviderApiException                validation could not be completed or the response was invalid
     * @throws RequestEncodingException            when the validation request body cannot be encoded
     * @throws RequestCoordinationException        when local request coordination cannot be established
     */
    public function check(ValidateDocumentRequest $request): void
    {
        // An HTTP error (e.g. 400 for an invalid document) propagates as
        // ProviderErrorResponseException with the provider's response; whether it
        // means a rejected document is decided by the host application.
        if (! $this->validation->send($request)) {
            throw new DocumentRejectedByProviderException();
        }
    }
}
