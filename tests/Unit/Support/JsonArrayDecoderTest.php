<?php

namespace DreaM1\EfakturaConnector\Tests\Unit\Support;

use DreaM1\EfakturaConnector\Support\JsonArrayDecoder;
use PHPUnit\Framework\TestCase;
use stdClass;

/** Checks JSON object and list decoding, nesting limits and rejection of invalid root values. */
class JsonArrayDecoderTest extends TestCase
{
    public function testDecodesJsonObjectsAndListsIntoArrays(): void
    {
        $this->assertSame(['status' => 'ACCEPTED'], JsonArrayDecoder::decodeArray('{"status":"ACCEPTED"}'));
        $this->assertSame([1, 2], JsonArrayDecoder::decodeArray('[1,2]'));
    }

    public function testReturnsNullForInvalidAndNonArrayJsonValues(): void
    {
        $this->assertNull(JsonArrayDecoder::decodeArray('{invalid-json'));
        $this->assertNull(JsonArrayDecoder::decodeArray('"scalar"'));
        $this->assertNull(JsonArrayDecoder::decodeArray('null'));
    }

    public function testHonorsTheRequestedNestingDepth(): void
    {
        $this->assertNull(JsonArrayDecoder::decodeArray('{"one":{"two":true}}', 2));
    }

    public function testDecodesListsWhilePreservingJsonObjectTypes(): void
    {
        $decoded = JsonArrayDecoder::decodeList('[{"name":"value"},[],{}]');

        $this->assertCount(3, $decoded);
        $this->assertInstanceOf(stdClass::class, $decoded[0]);
        $this->assertSame('value', $decoded[0]->name);
        $this->assertSame([], $decoded[1]);
        $this->assertInstanceOf(stdClass::class, $decoded[2]);
    }

    public function testDecodeListRejectsInvalidJsonAndNonListRoots(): void
    {
        $this->assertSame([], JsonArrayDecoder::decodeList('[]'));
        $this->assertNull(JsonArrayDecoder::decodeList('{invalid-json'));
        $this->assertNull(JsonArrayDecoder::decodeList('{"name":"value"}'));
        $this->assertNull(JsonArrayDecoder::decodeList('"scalar"'));
        $this->assertNull(JsonArrayDecoder::decodeList('null'));
        $this->assertNull(JsonArrayDecoder::decodeList('[{}]', 1));
    }

    public function testZeroDepthReturnsNullAndValidDepthIsAccepted(): void
    {
        $this->assertNull(JsonArrayDecoder::decodeArray('{"status":"ACCEPTED"}', 0));
        $this->assertNull(JsonArrayDecoder::decodeArray('{"status":"ACCEPTED"}', -1));
        $this->assertSame(
            ['status' => 'ACCEPTED'],
            JsonArrayDecoder::decodeArray('{"status":"ACCEPTED"}', 3)
        );
    }

    public function testRecognizesDecodedJsonListShapes(): void
    {
        $this->assertTrue(JsonArrayDecoder::isList([]));
        $this->assertTrue(JsonArrayDecoder::isList(['first', 'second']));
        $this->assertFalse(JsonArrayDecoder::isList(['name' => 'value']));
        $this->assertFalse(JsonArrayDecoder::isList(new stdClass()));
        $this->assertFalse(JsonArrayDecoder::isList('value'));
    }

    public function testConvertsDecodedJsonObjectsAndRejectsListShapes(): void
    {
        $this->assertSame([], JsonArrayDecoder::objectAsArray(new stdClass()));
        $this->assertSame(
            ['name' => 'value'],
            JsonArrayDecoder::objectAsArray((object) ['name' => 'value'])
        );
        $this->assertSame(
            [0 => 'first', 1 => 'second'],
            JsonArrayDecoder::objectAsArray(json_decode('{"0":"first","1":"second"}'))
        );
        $this->assertSame(
            ['name' => 'value'],
            JsonArrayDecoder::objectAsArray(['name' => 'value'])
        );
        $this->assertNull(JsonArrayDecoder::objectAsArray([]));
        $this->assertNull(JsonArrayDecoder::objectAsArray(['first', 'second']));
        $this->assertNull(JsonArrayDecoder::objectAsArray('value'));
    }
}
