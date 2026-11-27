<?php

namespace DreaM1\EfakturaConnector\Tests\Architecture;

use PHPUnit\Framework\TestCase;

/**
 * Keeps documented default values identical to the defaults in config/efaktura-connector.php.
 *
 * The website generator and the Laravel guide repeat these values by hand; this test fails when one of
 * them drifts from the code.
 */
final class DocumentedDefaultsTest extends TestCase
{
    public function testWebsiteEnvironmentTableMatchesConfigurationDefaults(): void
    {
        $defaults  = $this->configurationDefaults();
        $generator = $this->read('tools/build-site.js');
        $checked   = 0;

        preg_match_all("/\\['(EFAKTURA_[A-Z0-9_]+)',\\s*'([^']*)'\\]/", $generator, $rows, PREG_SET_ORDER);

        foreach ($rows as $row) {
            [, $name, $text] = $row;

            if (!isset($defaults[$name]) || !preg_match('/predvolene (zapnuté|\d+)(?: (s|ms|MiB|KiB))?(?=[;,.]|$| znakov)/u', $text, $match)) {
                continue;
            }

            $this->assertSame(
                $this->normalize($defaults[$name]),
                $this->documentedValue($name, $match[1], $match[2] ?? ''),
                sprintf('%s: the website says "%s" but the configuration default differs.', $name, $match[0])
            );
            ++$checked;
        }

        $this->assertGreaterThan(15, $checked, 'The website table should document many defaults.');
    }

    public function testLaravelGuideEnvExampleUsesConfigurationDefaultsForNumericSettings(): void
    {
        $defaults = $this->configurationDefaults();
        $guide    = $this->read('docs/wiki/Laravel.md');
        $checked  = 0;

        $this->assertSame(1, preg_match('/```dotenv\n(.*?)```/s', $guide, $block));
        preg_match_all('/^(EFAKTURA_[A-Z0-9_]+)=(\d+|true|false)\s*$/m', $block[1], $lines, PREG_SET_ORDER);

        foreach ($lines as $line) {
            if (!isset($defaults[$line[1]])) {
                continue;
            }

            $this->assertSame(
                $this->normalize($defaults[$line[1]]),
                $line[2],
                sprintf('%s in the Laravel guide example differs from the configuration default.', $line[1])
            );
            ++$checked;
        }

        $this->assertGreaterThan(15, $checked, 'The guide example should list many numeric defaults.');
    }

    /** @return array<string, string> environment variable name => resolved default as a string */
    private function configurationDefaults(): array
    {
        $source  = $this->read('config/efaktura-connector.php');
        $imports = [];

        preg_match_all('/^use ([A-Za-z0-9_\\\\]+);$/m', $source, $uses);

        foreach ($uses[1] as $fqcn) {
            $imports[substr((string) strrchr('\\' . $fqcn, '\\'), 1)] = $fqcn;
        }

        preg_match_all(
            "/env\\(\\s*'(EFAKTURA_[A-Z0-9_]+)'\\s*,\\s*([A-Za-z0-9_\\\\]+(?:::[A-Z0-9_]+)?|'[^']*')\\s*\\)/s",
            $source,
            $matches,
            PREG_SET_ORDER
        );

        $defaults = [];

        foreach ($matches as $match) {
            $value = $this->resolve($match[2], $imports);

            if ($value !== null) {
                $defaults[$match[1]] = $value;
            }
        }

        return $defaults;
    }

    /** @param array<string, string> $imports */
    private function resolve(string $expression, array $imports): ?string
    {
        if ($expression === 'true' || $expression === 'false') {
            return $expression;
        }

        if (preg_match('/^\d+$/', $expression) === 1) {
            return $expression;
        }

        if (preg_match("/^'([^']*)'$/", $expression, $string) === 1) {
            return $string[1];
        }

        if (preg_match('/^([A-Za-z0-9_]+)::([A-Z0-9_]+)$/', $expression, $constant) !== 1 || !isset($imports[$constant[1]])) {
            return null;
        }

        return $this->normalize(constant($imports[$constant[1]] . '::' . $constant[2]));
    }

    /** @param mixed $value */
    private function normalize($value): string
    {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        return (string) $value;
    }

    /** Converts a documented value and unit into the unit used by the setting's name. */
    private function documentedValue(string $name, string $value, string $unit): string
    {
        if ($value === 'zapnuté') {
            return 'true';
        }

        $number = (int) $value;
        $bytes  = ['MiB' => 1024 * 1024, 'KiB' => 1024];

        if (isset($bytes[$unit])) {
            return (string) ($number * $bytes[$unit]);
        }

        if ($unit === 's' && substr($name, -3) === '_MS') {
            return (string) ($number * 1000);
        }

        return (string) $number;
    }

    private function read(string $path): string
    {
        $contents = file_get_contents(dirname(__DIR__, 2) . '/' . $path);
        $this->assertNotFalse($contents, $path . ' must be readable.');

        return str_replace("\r\n", "\n", (string) $contents);
    }
}
