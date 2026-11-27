<?php

namespace DreaM1\EfakturaConnector\Tests\Unit\Infrastructure\EFaktura\Api;

use DreaM1\EfakturaConnector\Core\Exceptions\InvalidProviderResponseException;
use DreaM1\EfakturaConnector\Core\Pagination\PageToken;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Response\Integrator\IntegratorClientListFactory;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Response\Integrator\IntegratorParticipantListFactory;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Response\PeppolGateway\WebhookRegistryPageFactory;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Response\ProviderResponseReader;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Response\Sapi\ReceivedDocumentPageFactory;
use PHPUnit\Framework\TestCase;

/** A last page stays usable whether the provider omits the continuation token or sends it empty. */
class PageTokenResponseTest extends TestCase
{
    /**
     * @param mixed $absentOrEmpty Value of the nextPageToken field; the key is left out for the string 'absent'.
     * @dataProvider endOfListValues
     */
    public function testNoNextPageIsReportedForAbsentNullOrEmptyTokens($absentOrEmpty): void
    {
        foreach ($this->pageReaders() as $name => $read) {
            $this->assertNull($read($this->payload($name, $absentOrEmpty)), $name);
        }
    }

    /** @return array<string, array{mixed}> */
    public static function endOfListValues(): array
    {
        return [
            'absent'       => ['absent'],
            'null'         => [null],
            'empty string' => [''],
            'blank string' => ['   '],
            'whitespace'   => ["\t\r\n"],
        ];
    }

    public function testARealTokenIsKeptUnchanged(): void
    {
        foreach ($this->pageReaders() as $name => $read) {
            $token = $read($this->payload($name, ' token/with+chars== '));

            $this->assertInstanceOf(PageToken::class, $token, $name);
            $this->assertSame(' token/with+chars== ', $token->value(), $name);
        }
    }

    /**
     * @param mixed $invalid Value that is neither a string nor null.
     * @dataProvider nonStringValues
     */
    public function testANonStringTokenIsStillAnInvalidResponse($invalid): void
    {
        foreach ($this->pageReaders() as $name => $read) {
            try {
                $read($this->payload($name, $invalid));
                $this->fail($name . ' must reject a token that is not a string.');
            } catch (InvalidProviderResponseException $exception) {
                $this->assertStringContainsString('nextPageToken', $exception->getMessage(), $name);
            }
        }
    }

    /** @return array<string, array{mixed}> */
    public static function nonStringValues(): array
    {
        return [
            'integer' => [123],
            'boolean' => [true],
            'list'    => [['a']],
            'object'  => [['cursor' => 'a']],
        ];
    }

    public function testTheReaderMethodAppliesTheSameRuleDirectly(): void
    {
        $reader = new ProviderResponseReader(['next' => '  '], 'SyntheticSchema');

        $this->assertNull($reader->optionalPageToken('next'));
        $this->assertNull($reader->optionalPageToken('missing'));
        $this->assertSame('x', (new ProviderResponseReader(['next' => 'x'], 'SyntheticSchema'))->optionalPageToken('next')->value());
    }

    /** @return array<string, callable(array<string, mixed>): ?PageToken> */
    private function pageReaders(): array
    {
        return [
            'received documents' => static function (array $data): ?PageToken {
                return ReceivedDocumentPageFactory::fromArray($data)->nextPageToken();
            },
            'webhooks' => static function (array $data): ?PageToken {
                return WebhookRegistryPageFactory::fromArray($data)->nextPageToken();
            },
            'integrator clients' => static function (array $data): ?PageToken {
                return IntegratorClientListFactory::fromArray($data)->nextPageToken();
            },
            'integrator participants' => static function (array $data): ?PageToken {
                return IntegratorParticipantListFactory::fromArray($data)->nextPageToken();
            },
        ];
    }

    /**
     * @param  mixed                $token 'absent' omits the field; any other value is sent as given.
     * @return array<string, mixed>
     */
    private function payload(string $reader, $token): array
    {
        $keys = [
            'received documents'      => 'documents',
            'webhooks'                => 'webhooks',
            'integrator clients'      => 'clients',
            'integrator participants' => 'participants',
        ];
        $data = [$keys[$reader] => []];

        if ($token !== 'absent') {
            $data['nextPageToken'] = $token;
        }

        return $data;
    }
}
