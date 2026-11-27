<?php

namespace DreaM1\EfakturaConnector\Tests\Unit\Support\Exceptions;

use DreaM1\EfakturaConnector\Core\Exceptions\ConfigurationException;
use DreaM1\EfakturaConnector\Core\Exceptions\InvalidWebhookPayloadException;
use DreaM1\EfakturaConnector\Core\Exceptions\RequestEncodingException;
use DreaM1\EfakturaConnector\Core\Pagination\PageSize;
use DreaM1\EfakturaConnector\Domain\EFaktura\Participant;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Coordination\LockStorageException;
use DreaM1\EfakturaConnector\Support\Exceptions\ConnectorThrowable;
use DreaM1\EfakturaConnector\Support\Exceptions\InvalidInputException;
use DreaM1\EfakturaConnector\Support\Xml\XmlParseException;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Throwable;

/** Every failure the connector raises on purpose can be caught through one marker interface. */
final class ConnectorThrowableTest extends TestCase
{
    /**
     * @param Throwable $exception Exception raised by the connector.
     * @dataProvider connectorExceptionProvider
     */
    public function testConnectorExceptionsImplementTheMarkerInterface(Throwable $exception): void
    {
        $this->assertInstanceOf(ConnectorThrowable::class, $exception);
    }

    /** @return array<string, array{Throwable}> */
    public function connectorExceptionProvider(): array
    {
        return [
            'invalid input'   => [new InvalidInputException('x')],
            'webhook payload' => [new InvalidWebhookPayloadException('x')],
            'configuration'   => [new ConfigurationException('x')],
            'request encode'  => [new RequestEncodingException('x')],
            'lock storage'    => [new LockStorageException('x')],
            'xml parse'       => [XmlParseException::unsafe()],
        ];
    }

    public function testInvalidInputRemainsAnInvalidArgumentException(): void
    {
        $this->assertInstanceOf(InvalidArgumentException::class, new InvalidInputException('x'));
        $this->assertInstanceOf(InvalidInputException::class, new InvalidWebhookPayloadException('x'));
    }

    public function testRejectedValuesRaiseInvalidInput(): void
    {
        $this->expectException(InvalidInputException::class);

        PageSize::fromInt(0);
    }

    public function testMalformedParticipantRaisesAMarkedException(): void
    {
        $this->expectException(ConnectorThrowable::class);

        Participant::parse('not-a-participant');
    }
}
