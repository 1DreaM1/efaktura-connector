<?php

namespace DreaM1\EfakturaConnector\Laravel\Configuration;

use DreaM1\EfakturaConnector\Core\Contracts\DocumentDiscoveryProviderInterface;
use DreaM1\EfakturaConnector\Core\Contracts\DocumentSubmissionGatewayInterface;
use DreaM1\EfakturaConnector\Core\Contracts\DocumentTrackingProviderInterface;
use DreaM1\EfakturaConnector\Core\Contracts\ParticipantProfileProviderInterface;
use DreaM1\EfakturaConnector\Core\Contracts\PeppolGatewayDocumentSubmissionInterface;
use DreaM1\EfakturaConnector\Core\Contracts\ProviderDocumentValidationCheckInterface;
use DreaM1\EfakturaConnector\Core\Contracts\ReceivedDocumentProviderInterface;
use DreaM1\EfakturaConnector\Core\Contracts\RecipientCapabilityCheckInterface;
use DreaM1\EfakturaConnector\Core\Contracts\SapiClientRegistryInterface;
use DreaM1\EfakturaConnector\Core\Contracts\TokenHealthProviderInterface;
use DreaM1\EfakturaConnector\Core\Contracts\UblDraftSubmissionInterface;
use DreaM1\EfakturaConnector\Core\Contracts\WebhookRegistryInterface;

/**
 * @internal Binds the application-facing ports to the default SAPI client of the registry.
 *
 * Every port is taken from the client selected by `default_client`, so it shares that client's transport,
 * token provider and execution stack instead of a separately assembled default transport.
 */
final class LaravelDefaultClientPortsRegistrar
{
    private function __construct()
    {
    }

    /**
     * Registers the default-client ports, skipping contracts the host replaces in `laravel.services`.
     *
     * @param mixed                       $app           Laravel application container instance.
     * @param LaravelServiceConfiguration $configuration Validated Laravel service configuration.
     */
    public static function register($app, LaravelServiceConfiguration $configuration): void
    {
        foreach (self::ports() as $contract => $clientMethod) {
            if ($configuration->overrides($contract)) {
                continue;
            }

            $app->bind($contract, static function ($app) use ($clientMethod) {
                return $app->make(SapiClientRegistryInterface::class)->defaultClient()->{$clientMethod}();
            });
        }
    }

    /**
     * Returns each port contract with the SapiClientInterface method that provides it.
     *
     * @return array<class-string, string>
     */
    public static function ports(): array
    {
        return [
            DocumentSubmissionGatewayInterface::class       => 'documentSubmissionGateway',
            PeppolGatewayDocumentSubmissionInterface::class => 'peppolGatewayDocumentSubmission',
            DocumentTrackingProviderInterface::class        => 'documentTrackingProvider',
            ReceivedDocumentProviderInterface::class        => 'receivedDocumentProvider',
            WebhookRegistryInterface::class                 => 'webhookRegistry',
            ParticipantProfileProviderInterface::class      => 'participantProfileProvider',
            TokenHealthProviderInterface::class             => 'tokenHealthProvider',
            DocumentDiscoveryProviderInterface::class       => 'documentDiscoveryProvider',
            ProviderDocumentValidationCheckInterface::class => 'providerDocumentValidationCheck',
            RecipientCapabilityCheckInterface::class        => 'recipientCapabilityCheck',
            UblDraftSubmissionInterface::class              => 'ublDraftSubmission',
        ];
    }
}
