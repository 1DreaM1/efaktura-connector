<?php

namespace DreaM1\EfakturaConnector\Testing;

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
use DreaM1\EfakturaConnector\Support\Exceptions\InvariantViolationException;

/**
 * Offline SAPI client for host application tests. It never contacts a provider and holds no credentials.
 *
 * The document submission service is a recording FakeDocumentSubmissionGateway by default. Every other service
 * must be supplied with its use*() method, typically with a PHPUnit double of the narrow Core contract; asking
 * for a service that was not supplied fails with an InvariantViolationException instead of returning something
 * misleading.
 *
 * @api
 */
final class FakeSapiClient implements SapiClientInterface
{
    private string $name;
    private SapiClientProfile $profile;
    private FakeDocumentSubmissionGateway $submissions;
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
     * @param string                 $name    Alias the host uses to select this client.
     * @param SapiClientProfile|null $profile Non-secret profile to report; a placeholder is used when omitted.
     */
    public function __construct(string $name = 'fake', ?SapiClientProfile $profile = null)
    {
        $this->name        = $name;
        $this->profile     = $profile ?? new SapiClientProfile('https://fake.invalid', 'fake-client', 'document:send');
        $this->submissions = new FakeDocumentSubmissionGateway();
    }

    /** Returns the default recording submission fake, to queue outcomes and inspect what was sent. */
    public function submissions(): FakeDocumentSubmissionGateway
    {
        return $this->submissions;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function profile(): SapiClientProfile
    {
        return $this->profile;
    }

    /** Replaces the default submission fake. */
    public function useDocumentSubmissionGateway(DocumentSubmissionGatewayInterface $service): self
    {
        $this->documentSubmissionGateway = $service;

        return $this;
    }

    public function documentSubmissionGateway(): DocumentSubmissionGatewayInterface
    {
        return $this->documentSubmissionGateway ?? $this->submissions;
    }

    /** Supplies the Peppol Gateway batch submission service. */
    public function usePeppolGatewayDocumentSubmission(PeppolGatewayDocumentSubmissionInterface $service): self
    {
        $this->peppolGatewayDocumentSubmission = $service;

        return $this;
    }

    public function peppolGatewayDocumentSubmission(): PeppolGatewayDocumentSubmissionInterface
    {
        return $this->peppolGatewayDocumentSubmission ?? $this->notFaked('peppolGatewayDocumentSubmission');
    }

    /** Supplies the UBL draft submission service. */
    public function useUblDraftSubmission(UblDraftSubmissionInterface $service): self
    {
        $this->ublDraftSubmission = $service;

        return $this;
    }

    public function ublDraftSubmission(): UblDraftSubmissionInterface
    {
        return $this->ublDraftSubmission ?? $this->notFaked('ublDraftSubmission');
    }

    /** Supplies the document tracking service. */
    public function useDocumentTrackingProvider(DocumentTrackingProviderInterface $service): self
    {
        $this->documentTrackingProvider = $service;

        return $this;
    }

    public function documentTrackingProvider(): DocumentTrackingProviderInterface
    {
        return $this->documentTrackingProvider ?? $this->notFaked('documentTrackingProvider');
    }

    /** Supplies the received-document service. */
    public function useReceivedDocumentProvider(ReceivedDocumentProviderInterface $service): self
    {
        $this->receivedDocumentProvider = $service;

        return $this;
    }

    public function receivedDocumentProvider(): ReceivedDocumentProviderInterface
    {
        return $this->receivedDocumentProvider ?? $this->notFaked('receivedDocumentProvider');
    }

    /** Supplies the webhook registration service. */
    public function useWebhookRegistry(WebhookRegistryInterface $service): self
    {
        $this->webhookRegistry = $service;

        return $this;
    }

    public function webhookRegistry(): WebhookRegistryInterface
    {
        return $this->webhookRegistry ?? $this->notFaked('webhookRegistry');
    }

    /** Supplies the participant profile service. */
    public function useParticipantProfileProvider(ParticipantProfileProviderInterface $service): self
    {
        $this->participantProfileProvider = $service;

        return $this;
    }

    public function participantProfileProvider(): ParticipantProfileProviderInterface
    {
        return $this->participantProfileProvider ?? $this->notFaked('participantProfileProvider');
    }

    /** Supplies the token status service. */
    public function useTokenHealthProvider(TokenHealthProviderInterface $service): self
    {
        $this->tokenHealthProvider = $service;

        return $this;
    }

    public function tokenHealthProvider(): TokenHealthProviderInterface
    {
        return $this->tokenHealthProvider ?? $this->notFaked('tokenHealthProvider');
    }

    /** Supplies the participant discovery service. */
    public function useDocumentDiscoveryProvider(DocumentDiscoveryProviderInterface $service): self
    {
        $this->documentDiscoveryProvider = $service;

        return $this;
    }

    public function documentDiscoveryProvider(): DocumentDiscoveryProviderInterface
    {
        return $this->documentDiscoveryProvider ?? $this->notFaked('documentDiscoveryProvider');
    }

    /** Supplies the provider-side document validation check. */
    public function useProviderDocumentValidationCheck(ProviderDocumentValidationCheckInterface $service): self
    {
        $this->providerDocumentValidationCheck = $service;

        return $this;
    }

    public function providerDocumentValidationCheck(): ProviderDocumentValidationCheckInterface
    {
        return $this->providerDocumentValidationCheck ?? $this->notFaked('providerDocumentValidationCheck');
    }

    /** Supplies the recipient capability check. */
    public function useRecipientCapabilityCheck(RecipientCapabilityCheckInterface $service): self
    {
        $this->recipientCapabilityCheck = $service;

        return $this;
    }

    public function recipientCapabilityCheck(): RecipientCapabilityCheckInterface
    {
        return $this->recipientCapabilityCheck ?? $this->notFaked('recipientCapabilityCheck');
    }

    /** @return never */
    private function notFaked(string $service)
    {
        throw new InvariantViolationException(
            'FakeSapiClient has no ' . $service . ' service; supply one with use' . ucfirst($service) . '().'
        );
    }
}
