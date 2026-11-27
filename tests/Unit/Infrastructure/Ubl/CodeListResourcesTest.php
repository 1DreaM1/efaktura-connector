<?php

namespace DreaM1\EfakturaConnector\Tests\Unit\Infrastructure\Ubl;

use DreaM1\EfakturaConnector\Infrastructure\Ubl\CodeList\CodeListResources;
use DreaM1\EfakturaConnector\Support\Exceptions\ConnectorThrowable;
use DreaM1\EfakturaConnector\Support\Exceptions\InvariantViolationException;
use PHPUnit\Framework\TestCase;

/** A missing or damaged bundled resource must fail loudly instead of producing empty code lists. */
class CodeListResourcesTest extends TestCase
{
    /** @var string[] */
    private array $temporaryFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->temporaryFiles as $path) {
            @unlink($path);
        }

        parent::tearDown();
    }

    public function testRequiredReadReturnsTheBundledResource(): void
    {
        $resource = CodeListResources::readRequiredJsonArray(CodeListResources::ISO_4217_CURRENCIES_FILE);

        $this->assertSame(
            CodeListResources::readJsonArray(CodeListResources::ISO_4217_CURRENCIES_FILE),
            $resource
        );
        $this->assertNotSame([], $resource);
    }

    public function testRequiredReadRejectsAMissingResourceAndNamesIt(): void
    {
        try {
            CodeListResources::readRequiredJsonArray('missing-code-list-for-test.json');
            $this->fail('A missing resource must not be read as an empty list.');
        } catch (InvariantViolationException $exception) {
            $this->assertInstanceOf(ConnectorThrowable::class, $exception);
            $this->assertStringContainsString('"missing-code-list-for-test.json"', $exception->getMessage());
            $this->assertStringContainsString('missing or unreadable', $exception->getMessage());
        }
    }

    public function testRequiredReadRejectsAPathOutsideTheResourceDirectory(): void
    {
        $this->expectException(InvariantViolationException::class);
        $this->expectExceptionMessage('missing or unreadable');

        CodeListResources::readRequiredJsonArray('../../composer.json');
    }

    public function testRequiredReadRejectsMalformedJson(): void
    {
        $name = $this->createResource('{ this is not json');

        try {
            CodeListResources::readRequiredJsonArray($name);
            $this->fail('Malformed JSON must not be read as an empty list.');
        } catch (InvariantViolationException $exception) {
            $this->assertStringContainsString('"' . $name . '"', $exception->getMessage());
            $this->assertStringContainsString('not valid JSON', $exception->getMessage());
        }
    }

    public function testRequiredReadRejectsAJsonScalar(): void
    {
        $this->expectException(InvariantViolationException::class);

        CodeListResources::readRequiredJsonArray($this->createResource('"just a string"'));
    }

    public function testAFailedReadIsNotRememberedAndTheResourceCanBeReadAfterwards(): void
    {
        $name = 'code-list-recovery-test-' . bin2hex(random_bytes(6)) . '.json';
        $path = CodeListResources::path($name);

        try {
            CodeListResources::readRequiredJsonArray($name);
            $this->fail('The resource does not exist yet.');
        } catch (InvariantViolationException $exception) {
            $this->assertStringContainsString('missing or unreadable', $exception->getMessage());
        }

        $this->temporaryFiles[] = $path;
        file_put_contents($path, '{"codes":{"AA":"Alpha"}}');

        $this->assertSame(['codes' => ['AA' => 'Alpha']], CodeListResources::readRequiredJsonArray($name));
    }

    public function testTheLenientReadStillReturnsAnEmptyListForUnavailableResources(): void
    {
        $this->assertSame([], CodeListResources::readJsonArray('missing-code-list-for-test.json'));
        $this->assertSame([], CodeListResources::readJsonArray('../../composer.json'));
        $this->assertSame([], CodeListResources::readJsonArray($this->createResource('{ this is not json')));
    }

    public function testEveryBundledCodeListLoadsItsResourceThroughTheRequiredRead(): void
    {
        $directory = dirname(__DIR__, 4) . '/src/Infrastructure/Ubl/CodeList';
        $offenders = [];

        foreach (glob($directory . '/*.php') ?: [] as $file) {
            if (basename($file) === 'CodeListResources.php') {
                continue;
            }

            if (strpos((string) file_get_contents($file), 'readJsonArray(') !== false) {
                $offenders[] = basename($file);
            }
        }

        $this->assertSame(
            [],
            $offenders,
            'These code lists use the lenient read and would silently become empty: ' . implode(', ', $offenders)
        );
    }

    /** Creates a resource inside the code-list directory and returns its relative name. */
    private function createResource(string $contents): string
    {
        $name = 'code-list-test-' . bin2hex(random_bytes(6)) . '.json';
        $path = CodeListResources::path($name);

        $this->temporaryFiles[] = $path;
        file_put_contents($path, $contents);

        return $name;
    }
}
