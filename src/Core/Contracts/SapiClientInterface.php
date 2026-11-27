<?php

namespace DreaM1\EfakturaConnector\Core\Contracts;

use DreaM1\EfakturaConnector\Core\Configuration\SapiClientProfile;

/**
 * Application-facing services bound to one named OAuth client profile.
 * Intended for consumption by host applications; implementing it in a host class is not supported and new methods may be added in minor releases.
 */
interface SapiClientInterface
{
    /** Returns the alias used to select this client. */
    public function name(): string;

    /** Returns non-secret metadata about this client's provider profile. */
    public function profile(): SapiClientProfile;

    /** Returns document submission operations authenticated with this client's credentials. */
    public function documentSubmissionGateway(): DocumentSubmissionGatewayInterface;

    /** Returns Peppol Gateway batch submission operations authenticated with this client's credentials. */
    public function peppolGatewayDocumentSubmission(): PeppolGatewayDocumentSubmissionInterface;

    /** Returns UBL draft submission operations authenticated with this client's credentials. */
    public function ublDraftSubmission(): UblDraftSubmissionInterface;

    /** Returns document tracking operations authenticated with this client's credentials. */
    public function documentTrackingProvider(): DocumentTrackingProviderInterface;

    /** Returns SAPI received-document operations authenticated with this client's credentials. */
    public function receivedDocumentProvider(): ReceivedDocumentProviderInterface;

    /** Returns webhook registration operations authenticated with this client's credentials. */
    public function webhookRegistry(): WebhookRegistryInterface;

    /** Returns participant profile operations authenticated with this client's credentials. */
    public function participantProfileProvider(): ParticipantProfileProviderInterface;

    /** Returns token status operations authenticated with this client's credentials. */
    public function tokenHealthProvider(): TokenHealthProviderInterface;

    /** Returns participant discovery operations authenticated with this client's credentials. */
    public function documentDiscoveryProvider(): DocumentDiscoveryProviderInterface;

    /** Returns provider-side UBL checks authenticated with this client's credentials. */
    public function providerDocumentValidationCheck(): ProviderDocumentValidationCheckInterface;

    /** Returns recipient capability checks authenticated with this client's credentials. */
    public function recipientCapabilityCheck(): RecipientCapabilityCheckInterface;
}
