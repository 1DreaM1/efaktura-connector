<?php

namespace DreaM1\EfakturaConnector\Laravel\Validation;

use DreaM1\EfakturaConnector\Core\Configuration\ConnectorRuntimeConfigurationInterface;
use DreaM1\EfakturaConnector\Core\Contracts\LocalUblValidationInterface;
use DreaM1\EfakturaConnector\Core\Contracts\PeppolSchematronValidationInterface;
use DreaM1\EfakturaConnector\Core\Contracts\SchematronRunnerInterface;
use DreaM1\EfakturaConnector\Core\Contracts\UblSchemaValidationInterface;
use DreaM1\EfakturaConnector\Core\Ubl\Contracts\UblValidationStageInterface;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Metrics\ConnectorMetricsInterface;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Validation\LocalUblValidator;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Validation\Schema\UblSchemaValidator;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Validation\Schematron\FileSchematronConcurrencyLimiter;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Validation\Schematron\PeppolSchematronValidator;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Validation\Schematron\SaxonProcessExecutor;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Validation\Schematron\SaxonSchematronRunner;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Validation\Schematron\SchematronConcurrencyLimiterInterface;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Validation\Schematron\UblSchematronReportParser;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Validation\ValidationResources;
use DreaM1\EfakturaConnector\Laravel\Configuration\LaravelServiceConfiguration;
use Illuminate\Contracts\Container\BindingResolutionException;

/** Registers Laravel bindings for local UBL and Schematron validation. */
final class LaravelValidationServiceRegistrar
{
    /**
     * Registers validators, their runtime adapters and the optional local Schematron runner.
     *
     * @param  mixed                       $app           Laravel application container instance.
     * @param  LaravelServiceConfiguration $configuration Validated Laravel service configuration.
     * @throws BindingResolutionException  when a configured validation service cannot be resolved
     */
    public static function register($app, LaravelServiceConfiguration $configuration): void
    {
        if (! $configuration->overrides(SchematronConcurrencyLimiterInterface::class)) {
            $app->singleton(SchematronConcurrencyLimiterInterface::class, function ($app) {
                $runtimeConfiguration = $app->make(ConnectorRuntimeConfigurationInterface::class);
                $serviceConfiguration = $app->make(LaravelServiceConfiguration::class);

                return new FileSchematronConcurrencyLimiter(
                    $runtimeConfiguration->schematronMaxConcurrentValidations(),
                    $serviceConfiguration->schematronConcurrencyLockDirectory()
                );
            });
        }

        $app->bind(UblSchemaValidator::class, function ($app) {
            $serviceConfiguration = $app->make(LaravelServiceConfiguration::class);
            $runtimeConfiguration = $app->make(ConnectorRuntimeConfigurationInterface::class);

            return new UblSchemaValidator(
                $serviceConfiguration->validationResourceDirectory(),
                $runtimeConfiguration->validationMaxIssues()
            );
        });

        $app->bind(PeppolSchematronValidator::class, function ($app) {
            $serviceConfiguration = $app->make(LaravelServiceConfiguration::class);
            $runtimeConfiguration = $app->make(ConnectorRuntimeConfigurationInterface::class);

            return new PeppolSchematronValidator(
                $app->make(UblSchematronReportParser::class),
                $app->make(SchematronRunnerInterface::class),
                $serviceConfiguration->validationResourceDirectory(),
                $runtimeConfiguration->validationMaxIssues()
            );
        });

        if (! $configuration->overrides(UblSchemaValidationInterface::class)) {
            $app->bind(UblSchemaValidationInterface::class, function ($app) {
                return $app->make(UblSchemaValidator::class);
            });
        }

        if (! $configuration->overrides(PeppolSchematronValidationInterface::class)) {
            $app->bind(PeppolSchematronValidationInterface::class, function ($app) {
                return $app->make(PeppolSchematronValidator::class);
            });
        }

        if (! $configuration->overrides(LocalUblValidationInterface::class)) {
            $app->bind(LocalUblValidationInterface::class, function ($app) {
                return $app->make(LocalUblValidator::class);
            });
        }

        $app->bind(LocalUblValidator::class, function ($app) {
            $configuration = $app->make(LaravelServiceConfiguration::class);
            $stages        = [];

            foreach ($configuration->validationStageClasses() as $stageClass) {
                $stages[] = $app->make($stageClass);
            }

            return new LocalUblValidator(array_merge(
                $stages,
                $app->tagged(UblValidationStageInterface::class)
            ));
        });

        if (! $configuration->overrides(SchematronRunnerInterface::class)) {
            $app->bind(SchematronRunnerInterface::class, static function ($app) {
                return self::createSchematronRunner($app);
            });
        }
    }

    /**
     * Builds the runner and its bounded process executor.
     *
     * @param mixed $app Laravel application container instance.
     */
    private static function createSchematronRunner($app): SaxonSchematronRunner
    {
        $runtimeConfiguration = $app->make(ConnectorRuntimeConfigurationInterface::class);
        $serviceConfiguration = $app->make(LaravelServiceConfiguration::class);

        $metrics = $app->bound(ConnectorMetricsInterface::class)
            ? $app->make(ConnectorMetricsInterface::class)
            : null;

        $resourceDirectory = $serviceConfiguration->validationResourceDirectory()
            ?? ValidationResources::directory();
        $workingDirectory = $serviceConfiguration->validationWorkingDirectory()
            ?? $app->basePath();

        return new SaxonSchematronRunner(
            $resourceDirectory,
            $workingDirectory,
            new SaxonProcessExecutor(
                $runtimeConfiguration->schematronProcessTimeoutSeconds(),
                $runtimeConfiguration->schematronMaxConcurrentValidations(),
                $app->make(SchematronConcurrencyLimiterInterface::class),
                $metrics,
                $runtimeConfiguration->schematronMaxReportBytes(),
                $runtimeConfiguration->schematronMaxErrorOutputBytes(),
                $runtimeConfiguration->schematronMaxErrorDetailCharacters()
            ),
            $serviceConfiguration->javaBinary(),
            $runtimeConfiguration->schematronJavaMaxHeapMegabytes()
        );
    }
}
