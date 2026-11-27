<?php

namespace DreaM1\EfakturaConnector\Tests\Unit\Support\Xml;

use DOMDocument;
use DreaM1\EfakturaConnector\Support\Xml\LibxmlErrorScope;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/** Checks that libxml error state is restored after successful and throwing operations. */
class LibxmlErrorScopeTest extends TestCase
{
    public function testRestoresPreviousModeAfterSuccessfulOperation(): void
    {
        $previousMode = libxml_use_internal_errors();

        try {
            libxml_use_internal_errors(false);
            $result = LibxmlErrorScope::run(function () {
                $this->assertTrue(libxml_use_internal_errors());

                return 'completed';
            });

            $this->assertSame('completed', $result);
            $this->assertFalse(libxml_use_internal_errors());
        } finally {
            libxml_use_internal_errors($previousMode);
        }
    }

    public function testRestoresPreviousModeWhenOperationThrows(): void
    {
        $previousMode = libxml_use_internal_errors();

        try {
            libxml_use_internal_errors(false);
            $this->expectException(RuntimeException::class);

            LibxmlErrorScope::run(function () {
                throw new RuntimeException('Synthetic failure');
            });
        } finally {
            $this->assertFalse(libxml_use_internal_errors());
            libxml_use_internal_errors($previousMode);
        }
    }

    public function testClearsEarlierLibxmlErrorsBeforeRunningTheOperation(): void
    {
        $previousMode = libxml_use_internal_errors();

        try {
            libxml_use_internal_errors(true);
            libxml_clear_errors();
            $document = new DOMDocument();
            @$document->loadXML('<broken>');
            $this->assertNotEmpty(libxml_get_errors());

            LibxmlErrorScope::run(function () {
                $this->assertSame([], libxml_get_errors());
            });
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previousMode);
        }
    }
}
