<?php

namespace DreaM1\EfakturaConnector\Tests\Unit\Infrastructure;

use DreaM1\EfakturaConnector\Infrastructure\ResourcePath;
use PHPUnit\Framework\TestCase;

/** Checks platform-aware path joining and resolution relative to the package root. */
class ResourcePathTest extends TestCase
{
    public function testJoinsResourcePathsUsingPlatformSeparators(): void
    {
        $directory = rtrim(sys_get_temp_dir(), '/\\') . DIRECTORY_SEPARATOR . 'resource-root' . DIRECTORY_SEPARATOR;
        $expected  = rtrim($directory, '/\\') . DIRECTORY_SEPARATOR . 'nested'
            . DIRECTORY_SEPARATOR . 'resource.json';

        $this->assertSame($expected, ResourcePath::join($directory, 'nested/resource.json'));
        $this->assertSame($expected, ResourcePath::join($directory, 'nested\\resource.json'));
    }

    public function testResolvesPathsFromThePackageRoot(): void
    {
        $this->assertSame(
            ResourcePath::join(dirname(__DIR__, 3), 'resources/validation'),
            ResourcePath::packageDirectory('resources/validation')
        );
    }
}
