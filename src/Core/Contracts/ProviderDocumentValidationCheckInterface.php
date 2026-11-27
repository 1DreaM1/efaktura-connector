<?php

namespace DreaM1\EfakturaConnector\Core\Contracts;

use DreaM1\EfakturaConnector\Core\Documents\ValidateDocumentRequest;
use DreaM1\EfakturaConnector\Core\Exceptions\DocumentRejectedByProviderException;
use DreaM1\EfakturaConnector\Core\Exceptions\ProviderApiException;
use DreaM1\EfakturaConnector\Core\Exceptions\RequestCoordinationException;
use DreaM1\EfakturaConnector\Core\Exceptions\RequestEncodingException;

/**
 * Checks an outbound payload with the provider's validation endpoint without submitting it.
 * A successful validation response can still report that the document is invalid.
 */
interface ProviderDocumentValidationCheckInterface
{
    /**
     * @param  ValidateDocumentRequest             $request Participant and document payload to check.
     * @return void
     * @throws DocumentRejectedByProviderException when the provider returns valid=false
     * @throws ProviderApiException                when the request fails or the response is invalid
     * @throws RequestEncodingException            when the validation request body cannot be encoded
     * @throws RequestCoordinationException        when local request
     *                                             coordination cannot be established
     */
    public function check(ValidateDocumentRequest $request): void;
}
