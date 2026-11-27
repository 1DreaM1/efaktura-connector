<?php

namespace DreaM1\EfakturaConnector\Core\Configuration;

/**
 * Framework-independent HTTP and local validation settings used by connector services.
 *
 * This contract combines both runtime areas without coupling callers to a framework or a concrete
 * configuration implementation. Services that need only one area should depend on
 * {@see HttpRuntimeConfigurationInterface} or {@see LocalValidationRuntimeConfigurationInterface}.
 * Intended for consumption by host applications; implementing it in a host class is not supported and new methods may be added in minor releases.
 */
interface ConnectorRuntimeConfigurationInterface extends
    HttpRuntimeConfigurationInterface,
    LocalValidationRuntimeConfigurationInterface
{
}
