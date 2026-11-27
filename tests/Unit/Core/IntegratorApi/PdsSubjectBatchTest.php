<?php

namespace DreaM1\EfakturaConnector\Tests\Unit\Core\IntegratorApi;

use DreaM1\EfakturaConnector\Core\IntegratorApi\PdsSigningSecret;
use DreaM1\EfakturaConnector\Core\IntegratorApi\PdsSubject;
use DreaM1\EfakturaConnector\Core\IntegratorApi\PdsSubjectBatch;
use DreaM1\EfakturaConnector\Core\IntegratorApi\PdsVerificationToken;
use DreaM1\EfakturaConnector\Support\Timestamp;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use stdClass;

/** Tests typed values for Integrator API PDS subject submission. */
final class PdsSubjectBatchTest extends TestCase
{
    public function testNormalizesSubjectListAndPreservesTypedValues(): void
    {
        $subject = new PdsSubject(
            Timestamp::fromIso8601('2026-11-24T12:00:00Z'),
            '2022706466',
            'Synthetic Company',
            'info@example.test',
            '+421900000001',
            PdsVerificationToken::fromString('synthetic-verification-token')
        );
        $batch = new PdsSubjectBatch([5 => $subject]);

        $this->assertSame([$subject], $batch->subjects());
        $this->assertSame('2022706466', $batch->subjects()[0]->dic());
        $this->assertSame('synthetic-verification-token', $batch->subjects()[0]->verificationToken()->reveal());
    }

    public function testRejectsNonPdsSubjectItems(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('PDS subject batch items must be PdsSubject values.');

        new PdsSubjectBatch([new stdClass()]);
    }

    public function testRedactsSensitiveSubjectFieldsAndSigningSecretInDebugOutput(): void
    {
        $subject = new PdsSubject(
            null,
            'synthetic-dic',
            'Synthetic Company',
            'info@example.test',
            '+421900000001',
            PdsVerificationToken::fromString('synthetic-verification-token')
        );
        $batch  = new PdsSubjectBatch([$subject]);
        $secret = PdsSigningSecret::fromString('synthetic-pds-signing-secret');

        ob_start();
        var_dump($batch, $secret);
        $output = (string) ob_get_clean();

        $this->assertStringNotContainsString('synthetic-dic', $output);
        $this->assertStringNotContainsString('Synthetic Company', $output);
        $this->assertStringNotContainsString('info@example.test', $output);
        $this->assertStringNotContainsString('+421900000001', $output);
        $this->assertStringNotContainsString('synthetic-verification-token', $output);
        $this->assertStringNotContainsString('synthetic-pds-signing-secret', $output);
        $this->assertStringContainsString('[redacted]', $output);
    }
}
