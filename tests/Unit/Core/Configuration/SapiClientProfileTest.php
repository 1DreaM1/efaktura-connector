<?php

namespace DreaM1\EfakturaConnector\Tests\Unit\Core\Configuration;

use DreaM1\EfakturaConnector\Core\Configuration\SapiClientConfiguration;
use DreaM1\EfakturaConnector\Core\Configuration\SapiClientProfile;
use PHPUnit\Framework\TestCase;

/** Covers the profile as the single, credential-free description of a client's provider account. */
class SapiClientProfileTest extends TestCase
{
    public function testProfileIsReadFromTheConfiguredValues(): void
    {
        $configuration = new SapiClientConfiguration(
            ' https://sapi.synthetic.test/ ',
            'client-a',
            'secret-a',
            'document:send'
        );

        $profile = SapiClientProfile::fromConfiguration($configuration);

        $this->assertSame('https://sapi.synthetic.test', $profile->baseUrl());
        $this->assertSame('client-a', $profile->clientId());
        $this->assertSame('document:send', $profile->clientScope());
    }

    public function testProfileCanBeReadFromAnIncompleteConfiguration(): void
    {
        $configuration = new SapiClientConfiguration('not a url', '', '', '');

        $profile = SapiClientProfile::fromConfiguration($configuration);

        $this->assertSame('not a url', $profile->baseUrl());
        $this->assertSame('', $profile->clientId());
        $this->assertSame('', $profile->clientScope());
    }
}
