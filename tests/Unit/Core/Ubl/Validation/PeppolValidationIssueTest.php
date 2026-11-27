<?php

namespace DreaM1\EfakturaConnector\Tests\Unit\Core\Ubl\Validation;

use DreaM1\EfakturaConnector\Core\Ubl\Validation\PeppolValidationIssue;
use DreaM1\EfakturaConnector\Core\Ubl\Validation\PeppolValidationLine;
use PHPUnit\Framework\TestCase;

/** Checks Peppol finding fields and serialization of optional guidance and invoice-line context. */
class PeppolValidationIssueTest extends TestCase
{
    public function testExposesRawMessageAndIncludesOptionalLineDetails(): void
    {
        $line  = new PeppolValidationLine('Item', '1', '2', '10.00', '1', '20.00', 'EUR', []);
        $issue = new PeppolValidationIssue(
            'BR-CO-10',
            'Synthetic validation message',
            '/Invoice/cac:InvoiceLine',
            $line
        );

        $this->assertSame('Synthetic validation message', $issue->rawMessage());
        $this->assertSame([
            'rule_id'  => 'BR-CO-10',
            'message'  => 'Synthetic validation message',
            'severity' => 'error',
            'location' => '/Invoice/cac:InvoiceLine',
            'line'     => $line->toArray(),
        ], $issue->toArray());
        $this->assertNull((new PeppolValidationIssue('BR-1', 'Message', '/Invoice', null))->toArray()['line']);
        $this->assertSame('error', $issue->severity()->value());
    }
}
