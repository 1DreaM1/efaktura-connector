<?php

namespace DreaM1\EfakturaConnector\Tests\Unit\Core\Ubl\Validation;

use DreaM1\EfakturaConnector\Core\Ubl\Validation\UblSchemaValidationIssue;
use DreaM1\EfakturaConnector\Core\Ubl\Validation\UblValidationFinding;
use DreaM1\EfakturaConnector\Core\Ubl\Validation\UblValidationLocation;
use DreaM1\EfakturaConnector\Support\Exceptions\InvalidInputException;
use PHPUnit\Framework\TestCase;

/** A finding carries its location as one value object and still serializes the same shape. */
final class UblValidationFindingTest extends TestCase
{
    public function testSchemaIssueFindingCarriesLineAndColumn(): void
    {
        $finding = (new UblSchemaValidationIssue(12, 7, 'Element is not expected.'))->toFinding('xsd');

        $this->assertSame(12, $finding->locationDetails()->line());
        $this->assertSame(7, $finding->locationDetails()->column());
        $this->assertNull($finding->locationDetails()->path());
        $this->assertSame(['path' => null, 'line' => 12, 'column' => 7], $finding->location());
        $this->assertSame($finding->location(), $finding->toArray()['location']);
    }

    public function testLocationDefaultsToNothingKnown(): void
    {
        $location = new UblValidationLocation();

        $this->assertSame(['path' => null, 'line' => null, 'column' => null], $location->toArray());
    }

    public function testFindingKeepsExplicitLocationAndDefaults(): void
    {
        $issue   = new UblSchemaValidationIssue(1, 2, 'Message.');
        $finding = new UblValidationFinding(
            'schematron',
            $issue,
            'BR-01',
            'Rule failed.',
            new UblValidationLocation('/Invoice/cbc:ID')
        );

        $this->assertSame('/Invoice/cbc:ID', $finding->locationDetails()->path());
        $this->assertSame('error', $finding->severity()->value());
        $this->assertSame([], $finding->context());
        $this->assertNull($finding->guidance());
    }

    public function testBlankStageIsRejected(): void
    {
        $this->expectException(InvalidInputException::class);

        new UblValidationFinding(
            ' ',
            new UblSchemaValidationIssue(1, 1, 'Message.'),
            null,
            'Message.',
            new UblValidationLocation()
        );
    }
}
