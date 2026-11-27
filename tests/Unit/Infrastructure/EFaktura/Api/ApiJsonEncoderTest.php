<?php

namespace DreaM1\EfakturaConnector\Tests\Unit\Infrastructure\EFaktura\Api;

use DreaM1\EfakturaConnector\Core\Exceptions\RequestEncodingException;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Serialization\ApiJsonEncoder;
use JsonException;
use PHPUnit\Framework\TestCase;

/** Checks stable JSON request encoding and typed failures for invalid UTF-8 payloads. */
class ApiJsonEncoderTest extends TestCase
{
    public function testPreservesTheExistingApiJsonFormat(): void
    {
        $json = (new ApiJsonEncoder())->encode([
            'url'         => 'https://sapi.synthetic.test/a',
            'description' => 'faktúra',
        ]);

        $this->assertSame('{"url":"https://sapi.synthetic.test/a","description":"faktúra"}', $json);
    }

    public function testRejectsInvalidUtf8WithATypedException(): void
    {
        try {
            (new ApiJsonEncoder())->encode(['value' => "\xB1"]);
            $this->fail('Expected the invalid UTF-8 value to be rejected.');
        } catch (RequestEncodingException $exception) {
            $this->assertInstanceOf(JsonException::class, $exception->getPrevious());
        }
    }
}
