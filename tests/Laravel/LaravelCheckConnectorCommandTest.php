<?php

namespace DreaM1\EfakturaConnector\Tests\Laravel;

use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Validation\Schema\UblSchemaValidationStage;
use DreaM1\EfakturaConnector\Laravel\Console\CheckConnectorCommand;
use DreaM1\EfakturaConnector\Tests\Support\CoordinationCacheFake;
use Illuminate\Config\Repository;
use Illuminate\Encryption\Encrypter;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Facade;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

/** Tests the local Laravel connector diagnostic command. */
class LaravelCheckConnectorCommandTest extends TestCase
{
    private Application $application;
    private Repository $configuration;
    private CoordinationCacheFake $cache;

    /** Installs Laravel facades and synthetic connector dependencies. */
    protected function setUp(): void
    {
        parent::setUp();

        $this->application   = new Application(sys_get_temp_dir());
        $this->configuration = new Repository([
            'app'                => ['key' => 'synthetic-app-key'],
            'efaktura-connector' => [
                'default_client' => 'default',
                'clients'        => [
                    'default' => [
                        'base_url'      => 'https://provider.synthetic.test',
                        'client_id'     => 'synthetic-client',
                        'client_secret' => 'synthetic-secret',
                        'client_scope'  => 'document:send',
                    ],
                ],
                'laravel' => [
                    'cache_store' => 'connector-state',
                    'validation'  => [
                        'stages' => [UblSchemaValidationStage::class],
                    ],
                ],
            ],
        ]);
        $this->cache = new CoordinationCacheFake();

        Facade::clearResolvedInstances();
        Facade::setFacadeApplication($this->application);
        $this->application->instance('config', $this->configuration);
        $this->application->instance('cache', $this->cache);
        $this->application->instance('encrypter', new Encrypter(str_repeat('a', 32), 'AES-256-CBC'));
    }

    /** Clears facade state so this test cannot leak it to other Laravel integration tests. */
    protected function tearDown(): void
    {
        Facade::clearResolvedInstances();
        Facade::setFacadeApplication(null);

        parent::tearDown();
    }

    public function testCommandPassesWhenLocalConfigurationAndDependenciesAreReady(): void
    {
        [$exitCode, $output] = $this->runCommand();

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('PASS Default OAuth client: configured', $output);
        $this->assertStringContainsString('PASS Laravel encryption key: configured', $output);
        $this->assertStringContainsString('PASS Laravel cache lock', $output);
        $this->assertStringContainsString('No provider API request was sent.', $output);
        $this->assertSame('connector-state', $this->cache->lastStoreName());
    }

    public function testCommandReturnsFailureWithAnActionableMessageForMissingAppKey(): void
    {
        $this->configuration->set('app.key', '');

        [$exitCode, $output] = $this->runCommand();

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString(
            'FAIL Laravel encryption key: Set app.key so the default token cache can encrypt OAuth tokens.',
            $output
        );
        $this->assertStringContainsString('1 local connector check(s) failed.', $output);
    }

    public function testCommandReportsInvalidBridgeConfigurationWithoutHidingOtherChecks(): void
    {
        $this->configuration->set('efaktura-connector.laravel.validation.stages', []);

        [$exitCode, $output] = $this->runCommand();

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('FAIL Laravel bridge configuration:', $output);
        $this->assertStringContainsString('PASS Default OAuth client: configured', $output);
    }

    /** @return array{0: int, 1: string} */
    private function runCommand(): array
    {
        $command = new CheckConnectorCommand();
        $command->setLaravel($this->application);
        $tester   = new CommandTester($command);
        $exitCode = $tester->execute([]);

        return [$exitCode, $tester->getDisplay()];
    }
}
