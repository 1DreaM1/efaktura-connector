<?php

namespace DreaM1\EfakturaConnector\Core\Contracts;

use DreaM1\EfakturaConnector\Core\Configuration\IntegratorAccountConfiguration;

/** Creates an Integrator API client authenticated with an X-Integrator-Token. */
interface IntegratorAccountClientFactoryInterface
{
    /**
     * Builds a client scoped to one provider integrator account.
     *
     * @param  IntegratorAccountConfiguration   $configuration Provider URL, account identifier and API token.
     * @return IntegratorAccountClientInterface Client configured for that account.
     */
    public function create(IntegratorAccountConfiguration $configuration): IntegratorAccountClientInterface;
}
