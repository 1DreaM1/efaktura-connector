<?php

namespace DreaM1\EfakturaConnector\Tests\Unit\Infrastructure\EFaktura\Api\Response;

use DreaM1\EfakturaConnector\Core\Exceptions\InvalidProviderResponseException;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Response\ProviderResponseReader;
use PHPUnit\Framework\TestCase;
use stdClass;

/** Checks strict JSON field types, nullability, list/object roots and response-schema diagnostics. */
class ProviderResponseReaderTest extends TestCase
{
    public function testReadsDocumentedJsonTypesAndPreservesTheOriginalObject(): void
    {
        $data = [
            'name'         => 'Invoice',
            'optionalName' => null,
            'blank'        => '  ',
            'status'       => 'ACCEPTED',
            'createdAt'    => '2026-11-20T14:30:00Z',
            'valid'        => false,
            'count'        => 0,
            'tags'         => ['one', 'two'],
            'metadata'     => ['source' => 'provider'],
            'items'        => [['id' => 'item-1'], new stdClass()],
            'raw'          => [1, null, false],
        ];
        $response = new ProviderResponseReader($data, 'SyntheticResponse');

        $this->assertSame($data, $response->toArray());
        $this->assertSame('Invoice', $response->requiredString('name'));
        $this->assertNull($response->nullableString('optionalName'));
        $this->assertNull($response->optionalString('missing'));
        $this->assertNull($response->optionalStringAllowBlank('missing'));
        $this->assertSame('  ', $response->optionalStringAllowBlank('blank'));
        $this->assertNull($response->optionalNonBlankString('missing'));
        $this->assertSame('ACCEPTED', $response->requiredEnum('status', ['ACCEPTED', 'REJECTED']));
        $this->assertSame('2026-11-20T14:30:00Z', $response->requiredTimestamp('createdAt')->value());
        $this->assertFalse($response->requiredBool('valid'));
        $this->assertNull($response->optionalBool('missing'));
        $this->assertSame(0, $response->requiredInt('count'));
        $this->assertNull($response->optionalInt('missing'));
        $this->assertSame(['one', 'two'], $response->requiredStringList('tags'));
        $this->assertSame(['one', 'two'], $response->requiredEnumList('tags', ['one', 'two', 'three']));
        $this->assertSame(
            'provider',
            $response->requiredObject('metadata', 'SyntheticChild')->requiredString('source')
        );
        $this->assertNull($response->optionalObject('missing', 'SyntheticChild'));
        $this->assertSame(['item-1', null], array_map(
            static function (ProviderResponseReader $item): ?string {
                return $item->optionalString('id');
            },
            $response->requiredObjectList('items', 'SyntheticItem')
        ));
        $this->assertNull($response->optionalObjectList('missing', 'SyntheticItem'));
        $this->assertSame([1, null, false], $response->optionalList('raw'));
        $this->assertNull($response->optionalList('missing'));
    }

    public function testAcceptsNullForOptionalTypedFieldsButRejectsWrongTypes(): void
    {
        $response = new ProviderResponseReader([
            'text'            => 7,
            'blankIdentifier' => '  ',
            'valid'           => 0,
            'count'           => '1',
            'date'            => 'tomorrow',
            'status'          => 'UNKNOWN',
            'strings'         => ['ok', " \t"],
            'object'          => ['nested'],
            'objects'         => [['id' => 'ok'], 'bad'],
            'list'            => ['key' => 'value'],
        ], 'SyntheticResponse');

        $this->assertInvalidField(
            static function () use ($response): void {
                $response->optionalString('text');
            },
            'text'
        );
        $this->assertInvalidField(
            static function () use ($response): void {
                $response->optionalStringAllowBlank('text');
            },
            'text'
        );
        $this->assertInvalidField(
            static function () use ($response): void {
                $response->optionalNonBlankString('blankIdentifier');
            },
            'blankIdentifier'
        );
        $this->assertInvalidField(
            static function () use ($response): void {
                $response->optionalBool('valid');
            },
            'valid'
        );
        $this->assertInvalidField(
            static function () use ($response): void {
                $response->optionalInt('count');
            },
            'count'
        );
        $this->assertInvalidField(
            static function () use ($response): void {
                $response->optionalTimestamp('date');
            },
            'date'
        );
        $this->assertInvalidField(
            static function () use ($response): void {
                $response->optionalEnum('status', ['ACCEPTED']);
            },
            'status'
        );
        $this->assertInvalidField(
            static function () use ($response): void {
                $response->requiredTimestamp('date');
            },
            'date'
        );
        $this->assertInvalidField(
            static function () use ($response): void {
                $response->requiredStringList('strings');
            },
            'strings'
        );
        $this->assertInvalidField(
            static function () use ($response): void {
                $response->requiredObject('object', 'Child');
            },
            'object'
        );
        $this->assertInvalidField(
            static function () use ($response): void {
                $response->requiredObjectList('objects', 'Child');
            },
            'objects'
        );
        $this->assertInvalidField(
            static function () use ($response): void {
                $response->optionalList('list');
            },
            'list'
        );
        $this->assertInvalidField(static function (): void {
            (new ProviderResponseReader(['strings' => ['named' => 'value']], 'SyntheticResponse'))->requiredStringList('strings');
        }, 'strings');
    }

    public function testOptionalNonNullableAccessorsRejectExplicitNull(): void
    {
        $response = new ProviderResponseReader([
            'text'    => null,
            'status'  => null,
            'date'    => null,
            'valid'   => null,
            'count'   => null,
            'object'  => null,
            'objects' => null,
            'list'    => null,
        ], 'SyntheticResponse');

        $this->assertInvalidField(
            static function () use ($response): void {
                $response->optionalString('text');
            },
            'text'
        );
        $this->assertInvalidField(
            static function () use ($response): void {
                $response->optionalEnum('status', ['ACTIVE']);
            },
            'status'
        );
        $this->assertInvalidField(
            static function () use ($response): void {
                $response->optionalTimestamp('date');
            },
            'date'
        );
        $this->assertInvalidField(
            static function () use ($response): void {
                $response->optionalBool('valid');
            },
            'valid'
        );
        $this->assertInvalidField(
            static function () use ($response): void {
                $response->optionalInt('count');
            },
            'count'
        );
        $this->assertInvalidField(
            static function () use ($response): void {
                $response->optionalObject('object', 'Child');
            },
            'object'
        );
        $this->assertInvalidField(
            static function () use ($response): void {
                $response->optionalObjectList('objects', 'Child');
            },
            'objects'
        );
        $this->assertInvalidField(
            static function () use ($response): void {
                $response->optionalList('list');
            },
            'list'
        );
    }

    public function testNullableAccessorsAcceptMissingAndExplicitNullButValidateValues(): void
    {
        $response = new ProviderResponseReader(['text' => null, 'object' => null], 'SyntheticResponse');

        $this->assertNull($response->nullableString('text'));
        $this->assertNull($response->nullableString('missing'));
        $this->assertNull($response->nullableObject('object', 'Child'));
        $this->assertNull($response->nullableObject('missing', 'Child'));

        $values = new ProviderResponseReader([
            'text'   => 'value',
            'object' => ['name' => 'child'],
        ], 'SyntheticResponse');

        $this->assertSame('value', $values->nullableString('text'));
        $this->assertSame('child', $values->nullableObject('object', 'Child')->requiredString('name'));
    }

    public function testOptionalEnumAcceptsAnAllowedValueAndRejectsOthers(): void
    {
        $response = new ProviderResponseReader(['status' => 'ACCEPTED', 'other' => 'PENDING'], 'SyntheticResponse');

        $this->assertSame('ACCEPTED', $response->optionalEnum('status', ['ACCEPTED', 'REJECTED']));
        $this->assertNull($response->optionalEnum('missing', ['ACCEPTED', 'REJECTED']));
        $this->assertInvalidField(static function () use ($response): void {
            $response->optionalEnum('other', ['ACCEPTED', 'REJECTED']);
        }, 'other');
    }

    public function testRequiredEnumListRejectsValuesOutsideTheAllowedSet(): void
    {
        $response = new ProviderResponseReader(['events' => ['supported', 'unknown']], 'SyntheticResponse');

        $this->assertInvalidField(
            static function () use ($response): void {
                $response->requiredEnumList('events', ['supported']);
            },
            'events'
        );
    }

    public function testObjectReadersDistinguishEmptyObjectsFromEmptyLists(): void
    {
        $objects = new ProviderResponseReader([
            'object'  => new stdClass(),
            'objects' => [new stdClass()],
            'list'    => [],
        ], 'SyntheticResponse');

        $this->assertSame([], $objects->requiredObject('object', 'Child')->toArray());
        $this->assertSame([], $objects->requiredObjectList('objects', 'Child')[0]->toArray());

        $this->assertInvalidField(static function (): void {
            (new ProviderResponseReader(['object' => []], 'SyntheticResponse'))->requiredObject('object', 'Child');
        }, 'object');
        $this->assertInvalidField(static function (): void {
            (new ProviderResponseReader(['objects' => [[]]], 'SyntheticResponse'))->requiredObjectList('objects', 'Child');
        }, 'objects');
    }

    public function testAcceptsOpenApiRfc3339LowercaseDateTimeSeparators(): void
    {
        $response = new ProviderResponseReader(['date' => '2026-11-20t14:30:00z'], 'SyntheticResponse');

        $this->assertSame('2026-11-20t14:30:00z', $response->requiredTimestamp('date')->value());
    }

    public function testRejectsMissingRequiredValuesAndBlankStringsWithSchemaContext(): void
    {
        $response = new ProviderResponseReader(['blank' => " \t"], 'SyntheticResponse');

        $this->assertInvalidField(
            static function () use ($response): void {
                $response->requiredString('missing');
            },
            'missing'
        );
        $this->assertInvalidField(
            static function () use ($response): void {
                $response->requiredString('blank');
            },
            'blank'
        );
        $this->assertInvalidField(
            static function () use ($response): void {
                $response->requiredBool('missing');
            },
            'missing'
        );
        $this->assertInvalidField(
            static function () use ($response): void {
                $response->requiredInt('missing');
            },
            'missing'
        );
        $this->assertInvalidField(
            static function () use ($response): void {
                $response->requiredTimestamp('missing');
            },
            'missing'
        );
    }

    /** @param callable(): mixed $read Reader expected to reject the selected response field. */
    private function assertInvalidField(callable $read, string $field): void
    {
        try {
            $read();
            $this->fail('Expected an invalid provider response for field ' . $field . '.');
        } catch (InvalidProviderResponseException $exception) {
            $this->assertSame('SyntheticResponse', $exception->schema());
            $this->assertSame($field, $exception->field());
        }
    }
}
