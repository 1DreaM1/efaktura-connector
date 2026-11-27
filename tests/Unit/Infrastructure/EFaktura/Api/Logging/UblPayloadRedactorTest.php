<?php

namespace DreaM1\EfakturaConnector\Tests\Unit\Infrastructure\EFaktura\Api\Logging;

use DreaM1\EfakturaConnector\Domain\EFaktura\EFakturaDocumentLimits;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Logging\UblPayloadRedactor;
use PHPUnit\Framework\TestCase;

/** Tests redaction of sensitive UBL payload content. */
class UblPayloadRedactorTest extends TestCase
{
    public function testDtdIsRejectedAndRedacted(): void
    {
        $payload = '<!DOCTYPE Root [<!ENTITY synthetic "value">]><Root>&synthetic;</Root>';

        $this->assertSame('[redacted]', UblPayloadRedactor::redact($payload));
    }

    public function testMalformedXmlIsFullyRedacted(): void
    {
        $this->assertSame('[redacted]', UblPayloadRedactor::redact('<Invoice><cbc:Note>synthetic private note'));
    }

    public function testOversizedXmlIsRedactedWithoutParsing(): void
    {
        $payload = '<Root>'
            . str_repeat('synthetic-content', (int) ceil(EFakturaDocumentLimits::MAX_XML_SIZE_BYTES / 16))
            . '</Root>';

        $this->assertGreaterThan(EFakturaDocumentLimits::MAX_XML_SIZE_BYTES, strlen($payload));
        $this->assertSame('[redacted]', UblPayloadRedactor::redact($payload));
    }

    public function testAllowlistedUblFieldsRetainOnlySafeValues(): void
    {
        $cbcNamespace = 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2';
        $payload      = '<Invoice xmlns:cbc="' . $cbcNamespace . '">'
            . '<cbc:PayableAmount currencyID="Synthetic customer name">Synthetic amount</cbc:PayableAmount>'
            . '<cbc:DocumentCurrencyCode>EUR</cbc:DocumentCurrencyCode>'
            . '</Invoice>';

        $loggedPayload = UblPayloadRedactor::redact($payload);

        $this->assertStringNotContainsString('Synthetic', $loggedPayload);
        $this->assertStringContainsString(
            '<cbc:PayableAmount currencyID="[redacted]">[redacted]</cbc:PayableAmount>',
            $loggedPayload
        );
        $this->assertStringContainsString('<cbc:DocumentCurrencyCode>EUR</cbc:DocumentCurrencyCode>', $loggedPayload);
    }

    public function testCommentsAndProcessingInstructionsAreRemoved(): void
    {
        $cbcNamespace = 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2';
        $payload      = '<Invoice xmlns:cbc="' . $cbcNamespace . '">'
            . '<!-- synthetic private comment --><?synthetic private-instruction?>'
            . '<cbc:Name>Example Contact</cbc:Name></Invoice>';

        $loggedPayload = UblPayloadRedactor::redact($payload);

        $this->assertStringNotContainsString('synthetic private comment', $loggedPayload);
        $this->assertStringNotContainsString('synthetic private-instruction', $loggedPayload);
        $this->assertStringContainsString('<cbc:Name>[redacted]</cbc:Name>', $loggedPayload);
    }
}
