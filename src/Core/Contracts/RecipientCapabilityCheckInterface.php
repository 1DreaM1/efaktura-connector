<?php

namespace DreaM1\EfakturaConnector\Core\Contracts;

use DreaM1\EfakturaConnector\Core\Documents\OutboundDocumentPayload;
use DreaM1\EfakturaConnector\Core\Exceptions\ProviderApiException;
use DreaM1\EfakturaConnector\Core\Exceptions\RecipientUnsupportedException;
use DreaM1\EfakturaConnector\Core\Exceptions\RequestCoordinationException;
use DreaM1\EfakturaConnector\Core\Exceptions\RequestEncodingException;

/**
 * Checks whether discovery reports support for the recipient's document type and process.
 * This preflight check does not submit a document or guarantee a later delivery outcome.
 */
interface RecipientCapabilityCheckInterface
{
    /**
     * @param  OutboundDocumentPayload       $payload Document type, process and XML payload to check for recipient support.
     * @return void
     * @throws RecipientUnsupportedException when the recipient does not support the document
     * @throws ProviderApiException          when discovery cannot be completed
     * @throws RequestEncodingException      when the discovery request body cannot be encoded
     * @throws RequestCoordinationException  when local request
     *                                       coordination cannot be established
     */
    public function check(OutboundDocumentPayload $payload): void;
}
