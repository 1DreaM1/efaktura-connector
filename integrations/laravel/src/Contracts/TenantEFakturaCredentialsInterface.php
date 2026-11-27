<?php

namespace DreaM1\EfakturaConnector\Laravel\Contracts;

/**
 * Exposes one tenant's OAuth credentials to the Laravel SAPI client resolver.
 * This interface is frozen; new requirements will be added only as separate optional interfaces.
 */
interface TenantEFakturaCredentialsInterface
{
    /** Returns a stable, non-secret identifier for this tenant's SAPI client. */
    public function efakturaClientKey(): string;

    /** Returns the provider-issued OAuth client ID. */
    public function efakturaClientId(): string;

    /** Returns the provider-issued OAuth client secret from protected storage. */
    public function efakturaClientSecret(): string;

    /** Returns the explicit provider-issued OAuth scope. */
    public function efakturaClientScope(): string;
}
