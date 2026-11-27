<?php

namespace DreaM1\EfakturaConnector\Tests\Unit\Laravel;

use DreaM1\EfakturaConnector\Core\Configuration\SapiClientConfigurationInterface;
use DreaM1\EfakturaConnector\Core\Contracts\SapiClientFactoryInterface;
use DreaM1\EfakturaConnector\Core\Contracts\SapiClientInterface;
use DreaM1\EfakturaConnector\Core\Exceptions\ConfigurationException;
use DreaM1\EfakturaConnector\Laravel\Connection\TenantEFakturaClientResolver;
use DreaM1\EfakturaConnector\Laravel\Contracts\TenantEFakturaCredentialsInterface;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/** Tests client construction from tenant-owned OAuth credentials. */
class TenantEFakturaClientResolverTest extends TestCase
{
    public function testItBuildsAClientFromTenantCredentialsWithAnExplicitScope(): void
    {
        $tenant = $this->tenant(
            'customer-secret-key',
            'tenant-client-id',
            'tenant-client-secret',
            'document:send'
        );
        $client  = $this->createMock(SapiClientInterface::class);
        $factory = $this->createMock(SapiClientFactoryInterface::class);
        $factory->expects($this->once())
            ->method('create')
            ->with(
                'tenant-' . hash('sha256', 'customer-secret-key'),
                $this->callback(function (SapiClientConfigurationInterface $configuration): bool {
                    $this->assertSame('https://provider.synthetic.test', $configuration->baseUrl()->value());
                    $this->assertSame('tenant-client-id', $configuration->clientId()->value());
                    $this->assertSame('tenant-client-secret', $configuration->clientSecret()->reveal());
                    $this->assertSame(
                        'document:send',
                        $configuration->clientScope()->value()
                    );

                    return true;
                })
            )
            ->willReturn($client);

        $resolved = $this->resolver($factory)->forTenant($tenant);

        $this->assertSame($client, $resolved);
    }

    public function testItUsesTheTenantScopeAndReadsUpdatedCredentialsOnEveryResolution(): void
    {
        $received = [];
        $factory  = $this->createMock(SapiClientFactoryInterface::class);
        $factory->expects($this->exactly(2))
            ->method('create')
            ->willReturnCallback(function (string $name, SapiClientConfigurationInterface $configuration) use (&$received): SapiClientInterface {
                $received[] = [
                    'name'          => $name,
                    'client_id'     => $configuration->clientId()->value(),
                    'client_secret' => $configuration->clientSecret()->reveal(),
                    'scope'         => $configuration->clientScope()->value(),
                ];

                return $this->createMock(SapiClientInterface::class);
            });
        $resolver = $this->resolver($factory);

        $resolver->forTenant($this->tenant('customer-17', 'client-old', 'secret-old', 'document:send'));
        $resolver->forTenant($this->tenant('customer-17', 'client-new', 'secret-new', 'document:receive'));

        $this->assertSame(
            [
                [
                    'name'          => 'tenant-' . hash('sha256', 'customer-17'),
                    'client_id'     => 'client-old',
                    'client_secret' => 'secret-old',
                    'scope'         => 'document:send',
                ],
                [
                    'name'          => 'tenant-' . hash('sha256', 'customer-17'),
                    'client_id'     => 'client-new',
                    'client_secret' => 'secret-new',
                    'scope'         => 'document:receive',
                ],
            ],
            $received
        );
    }

    public function testItRejectsAnEmptyTenantKeyBeforeCreatingAClient(): void
    {
        $factory = $this->createMock(SapiClientFactoryInterface::class);
        $factory->expects($this->never())->method('create');

        $this->expectException(InvalidArgumentException::class);

        $this->resolver($factory)->forTenant($this->tenant('  ', 'client-id', 'client-secret'));
    }

    public function testItRejectsMissingCredentialsBeforeCreatingAClient(): void
    {
        $factory = $this->createMock(SapiClientFactoryInterface::class);
        $factory->expects($this->never())->method('create');

        $this->expectException(ConfigurationException::class);

        $this->resolver($factory)->forTenant($this->tenant('customer-18', 'client-id', ''));
    }

    public function testItRejectsMissingScopeBeforeCreatingAClient(): void
    {
        $factory = $this->createMock(SapiClientFactoryInterface::class);
        $factory->expects($this->never())->method('create');

        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage('OAuth scope is not configured');

        $this->resolver($factory)->forTenant($this->tenant('customer-19', 'client-id', 'client-secret'));
    }

    private function resolver(SapiClientFactoryInterface $factory): TenantEFakturaClientResolver
    {
        return new TenantEFakturaClientResolver($factory, 'https://provider.synthetic.test');
    }

    private function tenant(
        string $key,
        string $clientId,
        string $clientSecret,
        ?string $scope = null
    ): TenantEFakturaCredentialsInterface {
        return new class ($key, $clientId, $clientSecret, $scope) implements TenantEFakturaCredentialsInterface {
            private string $key;
            private string $clientId;
            private string $clientSecret;
            private ?string $scope;

            /**
             * @param string      $key          Stable non-secret client key for this tenant fixture.
             * @param string      $clientId     Synthetic OAuth client identifier.
             * @param string      $clientSecret Synthetic OAuth client secret.
             * @param string|null $scope        Synthetic OAuth scope, or null to test missing configuration.
             */
            public function __construct(string $key, string $clientId, string $clientSecret, ?string $scope)
            {
                $this->key          = $key;
                $this->clientId     = $clientId;
                $this->clientSecret = $clientSecret;
                $this->scope        = $scope;
            }

            /** {@inheritdoc} */
            public function efakturaClientKey(): string
            {
                return $this->key;
            }

            /** {@inheritdoc} */
            public function efakturaClientId(): string
            {
                return $this->clientId;
            }

            /** {@inheritdoc} */
            public function efakturaClientSecret(): string
            {
                return $this->clientSecret;
            }

            /** {@inheritdoc} */
            public function efakturaClientScope(): string
            {
                return $this->scope ?? '';
            }
        };
    }
}
