<?php

namespace DreaM1\EfakturaConnector\Tests\Unit\Infrastructure\EFaktura\Api\Transport;

use DreaM1\EfakturaConnector\Core\Configuration\ProviderBaseUrl;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Transport\Execution\ProviderRequestUrlResolver;
use DreaM1\EfakturaConnector\Support\Exceptions\InvariantViolationException;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/** Tests provider request URL composition and validation. */
class ProviderRequestUrlResolverTest extends TestCase
{
    public function testResolvesBasePathAndRequestPathIncludingQuery(): void
    {
        $this->assertSame(
            'https://provider.synthetic.test/sapi/v1/test?limit=10',
            ProviderRequestUrlResolver::resolve(
                ProviderBaseUrl::fromString('https://provider.synthetic.test/sapi/'),
                '/v1/test?limit=10'
            )
        );
    }

    public function testRequiresConfiguredBaseUrl(): void
    {
        $this->expectException(InvariantViolationException::class);

        ProviderRequestUrlResolver::resolve(null, '/v1/test');
    }

    /**
     * Rejects paths that cannot safely be appended to the configured provider base URL.
     *
     * @param string $path Request path that must fail validation.
     * @dataProvider invalidRequestPaths
     */
    public function testRejectsUnsafeOrNonRelativeRequestPaths(string $path): void
    {
        $this->expectException(InvalidArgumentException::class);

        ProviderRequestUrlResolver::resolve(
            ProviderBaseUrl::fromString('https://provider.synthetic.test'),
            $path
        );
    }

    /** @return array<array-key, array{string}> */
    public function invalidRequestPaths(): array
    {
        return [
            'empty'             => [''],
            'not absolute path' => ['v1/test'],
            'network path'      => ['//other.synthetic.test/v1/test'],
            'fragment'          => ['/v1/test#fragment'],
            'control character' => ["/v1/test\n"],
        ];
    }
}
