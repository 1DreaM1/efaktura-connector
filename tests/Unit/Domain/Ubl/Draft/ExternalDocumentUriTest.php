<?php

namespace DreaM1\EfakturaConnector\Tests\Unit\Domain\Ubl\Draft;

use DreaM1\EfakturaConnector\Domain\Ubl\Draft\ExternalDocumentUri;
use DreaM1\EfakturaConnector\Support\Exceptions\InvalidInputException;
use PHPUnit\Framework\TestCase;

/** An external document location must be a plain http or https URI that names a host. */
class ExternalDocumentUriTest extends TestCase
{
    /**
     * @dataProvider acceptedUris
     */
    public function testAcceptsHttpAndHttpsUrisAndKeepsTheirOriginalForm(string $uri): void
    {
        $this->assertSame($uri, ExternalDocumentUri::fromString($uri)->value());
    }

    /** @return array<string, array{string}> */
    public static function acceptedUris(): array
    {
        return [
            'https'                  => ['https://example.test/support.pdf'],
            'http'                   => ['http://example.test/support.pdf'],
            'upper-case scheme'      => ['HTTPS://Example.Test/File.pdf'],
            'port, path and query'   => ['https://example.test:8443/a/b.pdf?x=1&y=%20z'],
            'user-visible IPv4 host' => ['https://203.0.113.7/file.pdf'],
            'percent-encoded path'   => ['https://example.test/a%20b.pdf'],
        ];
    }

    /**
     * @dataProvider unsafeSchemes
     */
    public function testRefusesSchemesThatAreNotHttpOrHttps(string $uri): void
    {
        $this->expectException(InvalidInputException::class);
        $this->expectExceptionMessage('http or https');

        ExternalDocumentUri::fromString($uri);
    }

    /** @return array<string, array{string}> */
    public static function unsafeSchemes(): array
    {
        return [
            'javascript'        => ['javascript:alert(1)'],
            'javascript, cased' => ['JavaScript:alert(1)'],
            'data'              => ['data:text/plain;base64,QUJD'],
            'ftp'               => ['ftp://example.test/file.pdf'],
            'file with a host'  => ['file://fileserver/share/secret.pdf'],
            'urn'               => ['urn:isbn:0451450523'],
            'mailto'            => ['mailto:someone@example.test'],
            'custom scheme'     => ['app://example.test/open'],
        ];
    }

    /**
     * @dataProvider malformedUris
     */
    public function testRefusesMalformedUris(string $uri): void
    {
        $this->expectException(InvalidInputException::class);

        ExternalDocumentUri::fromString($uri);
    }

    /** @return array<string, array{string}> */
    public static function malformedUris(): array
    {
        return [
            'empty'                 => [''],
            'blank'                 => ['   '],
            'relative'              => ['example.test/file.pdf'],
            'scheme-relative'       => ['//example.test/file.pdf'],
            'missing host'          => ['https:///file.pdf'],
            'space in the path'     => ['https://example.test/a b.pdf'],
            'control character'     => ["https://example.test/a\nb"],
            'angle bracket'         => ['https://example.test/<a>'],
            'bad percent escape'    => ['https://example.test/%zz'],
            'http without any host' => ['http:/file.pdf'],
        ];
    }
}
