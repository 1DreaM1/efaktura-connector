<?php

namespace DreaM1\EfakturaConnector\Infrastructure\EFaktura\Connection;

use DreaM1\EfakturaConnector\Core\Configuration\HttpRequestBodyLimit;
use DreaM1\EfakturaConnector\Core\Configuration\SapiClientConfigurationInterface;
use DreaM1\EfakturaConnector\Core\Configuration\SapiClientProfile;
use DreaM1\EfakturaConnector\Core\Contracts\DocumentDiscoveryProviderInterface;
use DreaM1\EfakturaConnector\Core\Contracts\DocumentSubmissionGatewayInterface;
use DreaM1\EfakturaConnector\Core\Contracts\DocumentTrackingProviderInterface;
use DreaM1\EfakturaConnector\Core\Contracts\ParticipantProfileProviderInterface;
use DreaM1\EfakturaConnector\Core\Contracts\PeppolGatewayDocumentSubmissionInterface;
use DreaM1\EfakturaConnector\Core\Contracts\ProviderDocumentValidationCheckInterface;
use DreaM1\EfakturaConnector\Core\Contracts\ReceivedDocumentProviderInterface;
use DreaM1\EfakturaConnector\Core\Contracts\RecipientCapabilityCheckInterface;
use DreaM1\EfakturaConnector\Core\Contracts\SapiClientInterface;
use DreaM1\EfakturaConnector\Core\Contracts\TokenHealthProviderInterface;
use DreaM1\EfakturaConnector\Core\Contracts\UblDraftSubmissionInterface;
use DreaM1\EfakturaConnector\Core\Contracts\WebhookRegistryInterface;
use DreaM1\EfakturaConnector\Core\Ubl\Contracts\UblDocumentWriterInterface;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\ApiConnector;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Endpoints\PeppolGateway\CheckReceivingCapabilityEndpoint;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Endpoints\PeppolGateway\DeleteWebhookEndpoint;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Endpoints\PeppolGateway\GetMessageLevelStatusContentEndpoint;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Endpoints\PeppolGateway\GetParticipantProfileEndpoint;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Endpoints\PeppolGateway\GetSentDocumentEndpoint;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Endpoints\PeppolGateway\GetWebhookEndpoint;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Endpoints\PeppolGateway\ListMessageLevelStatusesEndpoint;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Endpoints\PeppolGateway\ListWebhooksEndpoint;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Endpoints\PeppolGateway\RegisterWebhookEndpoint;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Endpoints\PeppolGateway\SendDocumentsEndpoint;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Endpoints\PeppolGateway\UpdateWebhookEndpoint;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Endpoints\PeppolGateway\ValidateDocumentEndpoint;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Endpoints\Sapi\AcknowledgeReceivedDocumentEndpoint;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Endpoints\Sapi\GetReceivedDocumentEndpoint;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Endpoints\Sapi\GetTokenStatusEndpoint;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Endpoints\Sapi\ListReceivedDocumentsEndpoint;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Endpoints\Sapi\SendDocumentEndpoint;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Documents\Checks\PeppolGatewayDocumentValidationCheck;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Documents\Checks\RecipientCapabilityCheck;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Documents\PeppolGatewayDocumentSubmission;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Documents\PeppolGatewayDocumentTrackingProvider;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Documents\SapiDocumentSubmissionGateway;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Documents\SapiReceivedDocumentProvider;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Documents\UblDraftSubmission;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Webhooks\PeppolGatewayWebhookRegistry;

/** Application-facing provider operations authenticated with one named OAuth client. */
final class SapiClient implements SapiClientInterface
{
    private string $name;
    private SapiClientProfile $profile;
    private ApiConnector $api;
    private ApiConnector $tokenStatusApi;
    private UblDocumentWriterInterface $writer;
    private HttpRequestBodyLimit $requestBodyLimit;
    private ?DocumentSubmissionGatewayInterface $documentSubmissionGateway             = null;
    private ?PeppolGatewayDocumentSubmissionInterface $peppolGatewayDocumentSubmission = null;
    private ?UblDraftSubmissionInterface $ublDraftSubmission                           = null;
    private ?DocumentTrackingProviderInterface $documentTrackingProvider               = null;
    private ?ReceivedDocumentProviderInterface $receivedDocumentProvider               = null;
    private ?WebhookRegistryInterface $webhookRegistry                                 = null;
    private ?ParticipantProfileProviderInterface $participantProfileProvider           = null;
    private ?TokenHealthProviderInterface $tokenHealthProvider                         = null;
    private ?DocumentDiscoveryProviderInterface $documentDiscoveryProvider             = null;
    private ?ProviderDocumentValidationCheckInterface $providerDocumentValidationCheck = null;
    private ?RecipientCapabilityCheckInterface $recipientCapabilityCheck               = null;

    /**
     * Creates an isolated client service set.
     *
     * @param string                           $name             Configured client alias.
     * @param SapiClientConfigurationInterface $configuration    Client-specific provider and OAuth settings.
     * @param ApiConnector                     $api              Client-specific authenticated API connector.
     * @param UblDocumentWriterInterface       $writer           Shared local UBL writer.
     * @param HttpRequestBodyLimit|null        $requestBodyLimit Maximum Gateway batch request body size.
     * @param ApiConnector|null                $tokenStatusApi   Connector for the token status call that returns a 401
     *                                                           instead of replacing the token; defaults to $api.
     */
    public function __construct(
        string $name,
        SapiClientConfigurationInterface $configuration,
        ApiConnector $api,
        UblDocumentWriterInterface $writer,
        ?HttpRequestBodyLimit $requestBodyLimit = null,
        ?ApiConnector $tokenStatusApi = null
    ) {
        $this->name             = $name;
        $this->profile          = SapiClientProfile::fromConfiguration($configuration);
        $this->api              = $api;
        $this->tokenStatusApi   = $tokenStatusApi ?? $api;
        $this->writer           = $writer;
        $this->requestBodyLimit = $requestBodyLimit ?? HttpRequestBodyLimit::default();
    }

    /** {@inheritdoc} */
    public function name(): string
    {
        return $this->name;
    }

    /** {@inheritdoc} */
    public function profile(): SapiClientProfile
    {
        return $this->profile;
    }

    /** {@inheritdoc} */
    public function documentSubmissionGateway(): DocumentSubmissionGatewayInterface
    {
        if ($this->documentSubmissionGateway === null) {
            $this->documentSubmissionGateway = new SapiDocumentSubmissionGateway(new SendDocumentEndpoint($this->api));
        }

        return $this->documentSubmissionGateway;
    }

    /** {@inheritdoc} */
    public function peppolGatewayDocumentSubmission(): PeppolGatewayDocumentSubmissionInterface
    {
        if ($this->peppolGatewayDocumentSubmission === null) {
            $this->peppolGatewayDocumentSubmission = new PeppolGatewayDocumentSubmission(
                new SendDocumentsEndpoint($this->api, $this->requestBodyLimit)
            );
        }

        return $this->peppolGatewayDocumentSubmission;
    }

    /** {@inheritdoc} */
    public function ublDraftSubmission(): UblDraftSubmissionInterface
    {
        if ($this->ublDraftSubmission === null) {
            $this->ublDraftSubmission = new UblDraftSubmission(
                $this->writer,
                $this->documentSubmissionGateway()
            );
        }

        return $this->ublDraftSubmission;
    }

    /** {@inheritdoc} */
    public function documentTrackingProvider(): DocumentTrackingProviderInterface
    {
        if ($this->documentTrackingProvider === null) {
            $this->documentTrackingProvider = new PeppolGatewayDocumentTrackingProvider(
                new GetSentDocumentEndpoint($this->api),
                new ListMessageLevelStatusesEndpoint($this->api),
                new GetMessageLevelStatusContentEndpoint($this->api)
            );
        }

        return $this->documentTrackingProvider;
    }

    /** {@inheritdoc} */
    public function receivedDocumentProvider(): ReceivedDocumentProviderInterface
    {
        if ($this->receivedDocumentProvider === null) {
            $this->receivedDocumentProvider = new SapiReceivedDocumentProvider(
                new ListReceivedDocumentsEndpoint($this->api),
                new GetReceivedDocumentEndpoint($this->api),
                new AcknowledgeReceivedDocumentEndpoint($this->api)
            );
        }

        return $this->receivedDocumentProvider;
    }

    /** {@inheritdoc} */
    public function webhookRegistry(): WebhookRegistryInterface
    {
        if ($this->webhookRegistry === null) {
            $this->webhookRegistry = new PeppolGatewayWebhookRegistry(
                new RegisterWebhookEndpoint($this->api),
                new UpdateWebhookEndpoint($this->api),
                new DeleteWebhookEndpoint($this->api),
                new GetWebhookEndpoint($this->api),
                new ListWebhooksEndpoint($this->api)
            );
        }

        return $this->webhookRegistry;
    }

    /** {@inheritdoc} */
    public function participantProfileProvider(): ParticipantProfileProviderInterface
    {
        if ($this->participantProfileProvider === null) {
            $this->participantProfileProvider = new PeppolGatewayParticipantProfileProvider(
                new GetParticipantProfileEndpoint($this->api)
            );
        }

        return $this->participantProfileProvider;
    }

    /** {@inheritdoc} */
    public function tokenHealthProvider(): TokenHealthProviderInterface
    {
        if ($this->tokenHealthProvider === null) {
            $this->tokenHealthProvider = new SapiTokenHealthProvider(new GetTokenStatusEndpoint($this->tokenStatusApi));
        }

        return $this->tokenHealthProvider;
    }

    /** {@inheritdoc} */
    public function documentDiscoveryProvider(): DocumentDiscoveryProviderInterface
    {
        if ($this->documentDiscoveryProvider === null) {
            $this->documentDiscoveryProvider = new PeppolGatewayDiscoveryProvider(
                new CheckReceivingCapabilityEndpoint($this->api)
            );
        }

        return $this->documentDiscoveryProvider;
    }

    /** {@inheritdoc} */
    public function providerDocumentValidationCheck(): ProviderDocumentValidationCheckInterface
    {
        if ($this->providerDocumentValidationCheck === null) {
            $this->providerDocumentValidationCheck = new PeppolGatewayDocumentValidationCheck(
                new ValidateDocumentEndpoint($this->api)
            );
        }

        return $this->providerDocumentValidationCheck;
    }

    /** {@inheritdoc} */
    public function recipientCapabilityCheck(): RecipientCapabilityCheckInterface
    {
        if ($this->recipientCapabilityCheck === null) {
            $this->recipientCapabilityCheck = new RecipientCapabilityCheck($this->documentDiscoveryProvider());
        }

        return $this->recipientCapabilityCheck;
    }
}
