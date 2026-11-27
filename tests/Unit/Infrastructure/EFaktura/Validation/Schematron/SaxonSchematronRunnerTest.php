<?php

namespace DreaM1\EfakturaConnector\Tests\Unit\Infrastructure\EFaktura\Validation\Schematron;

use DreaM1\EfakturaConnector\Core\Ubl\Exceptions\LocalValidationInfrastructureException;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Validation\Schematron\SaxonSchematronRunner;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Validation\ValidationResources;
use DreaM1\EfakturaConnector\Infrastructure\ResourcePath;
use DreaM1\EfakturaConnector\Support\Exceptions\InvalidInputException;
use DreaM1\EfakturaConnector\Tests\Support\ReflectionAccess;
use DreaM1\EfakturaConnector\Tests\Support\RequiresSaxonRuntime;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/** Tests Saxon Schematron stylesheet execution. */
class SaxonSchematronRunnerTest extends TestCase
{
    use RequiresSaxonRuntime;

    public function testReturnsNoReportsWhenNoStylesheetsAreRequested(): void
    {
        $runner = new SaxonSchematronRunner(sys_get_temp_dir(), sys_get_temp_dir());

        $this->assertSame([], $runner->run('<r/>', []));
    }

    public function testRejectsMissingSaxonResourcesWithAnInfrastructureException(): void
    {
        $resourceDirectory = ResourcePath::join(sys_get_temp_dir(), 'efaktura-missing-saxon-' . uniqid());
        $this->assertTrue(mkdir($resourceDirectory));

        try {
            $runner = new SaxonSchematronRunner($resourceDirectory, sys_get_temp_dir());
            $runner->run('<r/>', ['synthetic.xsl']);
            $this->fail('Expected missing Saxon resources to be rejected.');
        } catch (LocalValidationInfrastructureException $exception) {
            $this->assertStringContainsString('Saxon resources', $exception->getMessage());
        } finally {
            rmdir($resourceDirectory);
        }
    }

    public function testUsesJavaFromJavaHomeWhenTheBinaryExists(): void
    {
        $javaHome     = ResourcePath::join(sys_get_temp_dir(), 'efaktura-java-home-' . uniqid());
        $binDirectory = ResourcePath::join($javaHome, 'bin');
        $this->assertTrue(mkdir($javaHome));
        $this->assertTrue(mkdir($binDirectory));
        $binaryName = DIRECTORY_SEPARATOR === '\\' ? 'java.exe' : 'java';
        $binary     = ResourcePath::join($binDirectory, $binaryName);
        $this->assertNotFalse(file_put_contents($binary, 'synthetic executable placeholder'));
        $previousJavaHome = getenv('JAVA_HOME');
        putenv('JAVA_HOME=' . $javaHome);

        try {
            $runner = new SaxonSchematronRunner(sys_get_temp_dir(), sys_get_temp_dir());
            $method = new ReflectionMethod($runner, 'javaBinary');
            ReflectionAccess::makeAccessible($method);

            $this->assertSame($binary, $method->invoke($runner));
        } finally {
            if ($previousJavaHome === false) {
                putenv('JAVA_HOME');
            } else {
                putenv('JAVA_HOME=' . $previousJavaHome);
            }

            unlink($binary);
            rmdir($binDirectory);
            rmdir($javaHome);
        }
    }

    public function testProcessesInputAndOutputLargerThanOperatingSystemPipeBuffers(): void
    {
        $this->requireSaxonRuntime();
        $stylesheet = tempnam(sys_get_temp_dir(), 'efaktura-xsl-');

        if ($stylesheet === false) {
            $this->fail('Unable to create a temporary test stylesheet.');
        }

        try {
            $xsl = '<?xml version="1.0"?>'
                . '<xsl:stylesheet version="2.0" xmlns:xsl="http://www.w3.org/1999/XSL/Transform">'
                . '<xsl:output method="xml"/>'
                . '<xsl:template match="/">'
                . '<out><xsl:for-each select="r/x"><item/></xsl:for-each></out>'
                . '</xsl:template>'
                . '</xsl:stylesheet>';
            $this->assertNotFalse(file_put_contents($stylesheet, $xsl));

            // Produce enough output to exceed typical OS pipe buffers and verify the runner does not deadlock.
            $itemCount = 12000;
            $xml       = '<r>' . str_repeat('<x/>', $itemCount) . '</r>';
            $runner    = new SaxonSchematronRunner(ValidationResources::directory(), sys_get_temp_dir());
            [$report]  = $runner->run($xml, [$stylesheet]);

            $this->assertSame($itemCount, substr_count($report->xml(), '<item'));
        } finally {
            if (is_file($stylesheet)) {
                unlink($stylesheet);
            }
        }
    }

    public function testRejectsReportLargerThanTheConfiguredLimit(): void
    {
        $this->requireSaxonRuntime();
        $stylesheet = tempnam(sys_get_temp_dir(), 'efaktura-xsl-');

        if ($stylesheet === false) {
            $this->fail('Unable to create a temporary test stylesheet.');
        }

        try {
            $outputChunk = str_repeat('x', 8192);
            $xsl         = '<?xml version="1.0"?>'
                . '<xsl:stylesheet version="2.0" xmlns:xsl="http://www.w3.org/1999/XSL/Transform">'
                . '<xsl:output method="text"/>'
                . '<xsl:template match="/">'
                . '<xsl:for-each select="1 to 4097"><xsl:text>' . $outputChunk . '</xsl:text></xsl:for-each>'
                . '</xsl:template>'
                . '</xsl:stylesheet>';
            $this->assertNotFalse(file_put_contents($stylesheet, $xsl));

            try {
                $runner = new SaxonSchematronRunner(ValidationResources::directory(), sys_get_temp_dir());
                $runner->run('<r/>', [$stylesheet]);
                $this->fail('Reports larger than the configured limit must be rejected.');
            } catch (LocalValidationInfrastructureException $exception) {
                $this->assertStringContainsString('exceeded the safe size limit', $exception->getMessage());
            }
        } finally {
            if (is_file($stylesheet)) {
                unlink($stylesheet);
            }
        }
    }

    public function testHeapLimitIsPassedToJavaBeforeTheClassPath(): void
    {
        $command = $this->commandFor(new SaxonSchematronRunner(sys_get_temp_dir(), sys_get_temp_dir(), null, 'java', 768));

        $this->assertSame('java', $command[0]);
        $this->assertSame('-Xmx768m', $command[1]);
        $this->assertSame('-cp', $command[2]);
        $this->assertContains('-dtd:off', $command);
        $this->assertContains('-ext:off', $command);
        $this->assertContains('-xsl:synthetic.xsl', $command);
    }

    public function testNoHeapOptionIsPassedByDefault(): void
    {
        $command = $this->commandFor(new SaxonSchematronRunner(sys_get_temp_dir(), sys_get_temp_dir(), null, 'java'));

        $this->assertSame('-cp', $command[1]);

        foreach ($command as $argument) {
            $this->assertStringStartsNotWith('-Xmx', $argument);
        }
    }

    public function testRejectsANonPositiveHeapLimit(): void
    {
        $this->expectException(InvalidInputException::class);

        new SaxonSchematronRunner(sys_get_temp_dir(), sys_get_temp_dir(), null, null, 0);
    }

    public function testJavaAcceptsTheConfiguredHeapLimit(): void
    {
        $this->requireSaxonRuntime();
        $stylesheet = $this->textStylesheet('<xsl:text>ok</xsl:text>');

        try {
            $runner   = new SaxonSchematronRunner(ValidationResources::directory(), sys_get_temp_dir(), null, null, 256);
            [$report] = $runner->run('<r/>', [$stylesheet]);

            $this->assertSame('ok', trim($report->xml()));
        } finally {
            @unlink($stylesheet);
        }
    }

    public function testAHeapLimitTooSmallForTheWorkEndsInAnInfrastructureException(): void
    {
        $this->requireSaxonRuntime();
        $stylesheet = $this->textStylesheet(
            '<xsl:value-of select="string-length(string-join((1 to 30000000) ! string(.), \',\'))"/>'
        );

        try {
            $runner = new SaxonSchematronRunner(ValidationResources::directory(), sys_get_temp_dir(), null, null, 64);
            $runner->run('<r/>', [$stylesheet]);
            $this->fail('A process that exceeds its heap limit must be reported as a failure.');
        } catch (LocalValidationInfrastructureException $exception) {
            $this->assertStringContainsString('failed with exit code', $exception->getMessage());
            $this->assertStringContainsString('OutOfMemoryError', $exception->getMessage());
        } finally {
            @unlink($stylesheet);
        }
    }

    /** @return list<string> */
    private function commandFor(SaxonSchematronRunner $runner): array
    {
        $method = new ReflectionMethod($runner, 'commandForStylesheet');
        ReflectionAccess::makeAccessible($method);

        return $method->invoke($runner, 'synthetic.xsl', 'java', 'classes.jar');
    }

    /** Creates a temporary text-output stylesheet whose root template runs the given XSLT body. */
    private function textStylesheet(string $body): string
    {
        $path = tempnam(sys_get_temp_dir(), 'efaktura-xsl-');

        if ($path === false) {
            $this->fail('Unable to create a temporary test stylesheet.');
        }

        file_put_contents(
            $path,
            '<?xml version="1.0"?>'
            . '<xsl:stylesheet version="2.0" xmlns:xsl="http://www.w3.org/1999/XSL/Transform">'
            . '<xsl:output method="text"/>'
            . '<xsl:template match="/">' . $body . '</xsl:template>'
            . '</xsl:stylesheet>'
        );

        return $path;
    }
}
