<?php

namespace DreaM1\EfakturaConnector\Tests\Unit\Support;

use DreaM1\EfakturaConnector\Support\HttpsUrlValidator;
use PHPUnit\Framework\TestCase;

/** Checks recognition of absolute HTTPS authorities independently of endpoint-specific URL rules. */
class HttpsUrlValidatorTest extends TestCase
{
    public function testRecognizesHttpsSchemesWithoutApplyingEndpointSpecificUrlRules(): void
    {
        $this->assertTrue(HttpsUrlValidator::hasHttpsScheme('https'));
        $this->assertTrue(HttpsUrlValidator::hasHttpsScheme('HTTPS'));
        $this->assertFalse(HttpsUrlValidator::hasHttpsScheme('http'));
        $this->assertFalse(HttpsUrlValidator::hasHttpsScheme(null));
    }

    public function testChecksAnAbsoluteHttpsUrl(): void
    {
        $this->assertTrue(HttpsUrlValidator::isAbsoluteHttpsUrl('https://example.test/callback'));
        $this->assertTrue(HttpsUrlValidator::isAbsoluteHttpsUrl('HTTPS://example.test/callback'));
        $this->assertFalse(HttpsUrlValidator::isAbsoluteHttpsUrl('http://example.test/callback'));
        $this->assertFalse(HttpsUrlValidator::isAbsoluteHttpsUrl('/callback'));
        $this->assertFalse(HttpsUrlValidator::isAbsoluteHttpsUrl('https:///callback'));
        $this->assertFalse(HttpsUrlValidator::isAbsoluteHttpsUrl('https://user:password@example.test/callback'));
    }
}
