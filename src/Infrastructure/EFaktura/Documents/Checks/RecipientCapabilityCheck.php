<?php

namespace DreaM1\EfakturaConnector\Infrastructure\EFaktura\Documents\Checks;

use DreaM1\EfakturaConnector\Core\Connection\CheckReceivingCapabilityRequest;
use DreaM1\EfakturaConnector\Core\Contracts\DocumentDiscoveryProviderInterface;
use DreaM1\EfakturaConnector\Core\Contracts\RecipientCapabilityCheckInterface;
use DreaM1\EfakturaConnector\Core\Documents\OutboundDocumentPayload;
use DreaM1\EfakturaConnector\Core\Exceptions\ProviderApiException;
use DreaM1\EfakturaConnector\Core\Exceptions\RecipientUnsupportedException;
use DreaM1\EfakturaConnector\Core\Exceptions\RequestCoordinationException;
use DreaM1\EfakturaConnector\Core\Exceptions\RequestEncodingException;

/**
 * Uses Peppol discovery to check whether the recipient advertises this document type and process.
 * A positive result is a preflight capability signal, not a guarantee of document delivery.
 */
final class RecipientCapabilityCheck implements RecipientCapabilityCheckInterface
{
    private DocumentDiscoveryProviderInterface $discovery;

    /**
     * @param DocumentDiscoveryProviderInterface $discovery Provider discovery service used to check recipient support.
     */
    public function __construct(DocumentDiscoveryProviderInterface $discovery)
    {
        $this->discovery = $discovery;
    }

    /**
     * Completes the discovery check without submitting the document.
     *
     * @param  OutboundDocumentPayload       $payload Document type and process identifiers to check for the recipient.
     * @return void
     * @throws RecipientUnsupportedException the recipient does not receive this document type and process
     * @throws ProviderApiException          discovery could not be completed
     * @throws RequestEncodingException      when the discovery request body cannot be encoded
     * @throws RequestCoordinationException  when local request coordination cannot be established
     */
    public function check(OutboundDocumentPayload $payload): void
    {
        $result = $this->discovery->receives(
            new CheckReceivingCapabilityRequest(
                $payload->receiver(),
                $payload->documentTypeId(),
                $payload->processId()
            )
        );

        if (! $result->receives) {
            throw new RecipientUnsupportedException();
        }
    }
}
