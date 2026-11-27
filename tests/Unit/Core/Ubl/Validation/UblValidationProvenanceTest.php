<?php

namespace DreaM1\EfakturaConnector\Tests\Unit\Core\Ubl\Validation;

use DreaM1\EfakturaConnector\Core\Ubl\Validation\UblValidationProvenance;
use PHPUnit\Framework\TestCase;

/** Tests validation artifact provenance helpers. */
class UblValidationProvenanceTest extends TestCase
{
    public function testFileHashesAreAddedOnlyForReadableArtifacts(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'efaktura-provenance-');

        if ($path === false) {
            $this->fail('A temporary file could not be created.');
        }

        $contents = 'synthetic validation resource';

        try {
            $this->assertSame(strlen($contents), file_put_contents($path, $contents));

            $result = UblValidationProvenance::withFileHashes(
                ['runtime.java' => '17'],
                [
                    'artifact.rules_sha256'   => $path,
                    'artifact.missing_sha256' => $path . '.missing',
                ]
            );

            $this->assertSame('17', $result['runtime.java']);
            $this->assertSame(hash('sha256', $contents), $result['artifact.rules_sha256']);
            $this->assertArrayNotHasKey('artifact.missing_sha256', $result);
        } finally {
            if (is_file($path)) {
                unlink($path);
            }
        }
    }
}
