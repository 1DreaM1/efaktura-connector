<?php

namespace DreaM1\EfakturaConnector\Tests\Architecture;

use PHPUnit\Framework\TestCase;

/** Keeps the configuration example in the Laravel guide identical to the file the package publishes. */
final class DocumentedConfigurationTest extends TestCase
{
    public function testLaravelGuideShowsThePublishedConfigurationFileVerbatim(): void
    {
        $root     = dirname(__DIR__, 2);
        $guide    = (string) file_get_contents($root . '/docs/wiki/Laravel.md');
        $shipped  = (string) file_get_contents($root . '/config/efaktura-connector.php');
        $heading  = '#### Kompletný súbor `config/efaktura-connector.php`';
        $position = strpos($guide, $heading);

        $this->assertNotFalse($position, 'The guide must keep the full configuration example section.');
        $this->assertSame(1, preg_match('/```php\n(.*?)\n```/s', substr($guide, (int) $position), $matches));
        $this->assertSame(
            rtrim(str_replace("\r\n", "\n", $shipped)),
            rtrim(str_replace("\r\n", "\n", $matches[1])),
            'Copy config/efaktura-connector.php into the example in docs/wiki/Laravel.md.'
        );
    }
}
