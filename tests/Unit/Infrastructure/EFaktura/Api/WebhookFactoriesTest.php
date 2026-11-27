<?php

namespace DreaM1\EfakturaConnector\Tests\Unit\Infrastructure\EFaktura\Api;

use DreaM1\EfakturaConnector\Core\Exceptions\InvalidProviderResponseException;
use DreaM1\EfakturaConnector\Core\Webhooks\Webhook;
use DreaM1\EfakturaConnector\Core\Webhooks\WebhookRegistrationResponse;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Response\PeppolGateway\WebhookFactory;
use DreaM1\EfakturaConnector\Infrastructure\EFaktura\Api\Response\PeppolGateway\WebhookRegistrationResponseFactory;
use DreaM1\EfakturaConnector\Tests\Support\ApiFixtures;
use PHPUnit\Framework\TestCase;

/** Tests direct webhook Core mapping and preservation of provider response validation. */
final class WebhookFactoriesTest extends TestCase
{
    public function testFactoriesReturnCoreWebhookModelsWithEquivalentFields(): void
    {
        $plain   = WebhookFactory::fromArray(ApiFixtures::webhook('wh-9'));
        $created = WebhookRegistrationResponseFactory::fromArray(
            ApiFixtures::webhookCreate('wh-9', 'secret-9')
        );

        $this->assertInstanceOf(Webhook::class, $plain);
        $this->assertInstanceOf(WebhookRegistrationResponse::class, $created);
        $this->assertSame('secret-9', $created->signingSecret()->reveal());
        $this->assertSame($plain->id()->value(), $created->id()->value());
        $this->assertSame($plain->url()->value(), $created->url()->value());
        $this->assertSame($plain->status()->value(), $created->status()->value());
        $this->assertSame($plain->createdAt()->value(), $created->createdAt()->value());
        $this->assertSame($plain->updatedAt()->value(), $created->updatedAt()->value());
        $this->assertSame(count($plain->events()), count($created->events()));
    }

    public function testFactoryPreservesUnknownProviderWebhookValues(): void
    {
        $webhook = WebhookFactory::fromArray(ApiFixtures::webhook('wh-future', [
            'status' => 'PAUSED',
            'events' => ['future.event'],
        ]));

        $this->assertSame('PAUSED', $webhook->status()->value());
        $this->assertFalse($webhook->status()->isKnown());
        $this->assertSame('future.event', $webhook->events()[0]->value());
        $this->assertFalse($webhook->events()[0]->isSupported());
    }

    /**
     * @param class-string<WebhookFactory|WebhookRegistrationResponseFactory> $factory Factory for the schema under test.
     * @param array<string, mixed>                                            $data    Provider body carrying an insecure URL.
     * @param string                                                          $schema  Schema name expected in the error.
     * @dataProvider insecureUrlProvider
     */
    public function testInvalidUrlNamesTheSchemaOfTheResponseThatCarriedIt(
        string $factory,
        array $data,
        string $schema
    ): void {
        try {
            $factory::fromArray($data);
            $this->fail('An insecure webhook URL must be rejected.');
        } catch (InvalidProviderResponseException $exception) {
            $this->assertSame($schema, $exception->schema());
            $this->assertStringContainsString('"url"', $exception->getMessage());
        }
    }

    /** @return array<string, array{string, array<string, mixed>, string}> */
    public function insecureUrlProvider(): array
    {
        $insecure = ['url' => 'http://insecure.test'];

        return [
            'plain webhook' => [WebhookFactory::class, $insecure + ApiFixtures::webhook('wh-1'), 'Webhook'],
            'registration'  => [
                WebhookRegistrationResponseFactory::class,
                $insecure + ApiFixtures::webhookCreate('wh-1'),
                'WebhookCreateResponse',
            ],
        ];
    }
}
