# eFaktura Connector

Framework-independent PHP library for the Slovak e-invoicing APIs of DataHosting: SAPI-SK, Peppol Gateway and the Integrator API. It also reads, writes and validates UBL 2.1 Invoice and CreditNote documents locally.

The detailed guides in [`docs/wiki/`](docs/wiki/Home.md) are written in Slovak. This page is the English entry point; code, class names, exception messages and the OpenAPI snapshot are in English.

## What it provides

- Typed endpoints for SAPI (OAuth tokens, submission, received documents, tracking) and Peppol Gateway (receiving-capability checks, batch submission, participant profiles, message-level statuses, validation, webhook registry), plus a separate Integrator API client.
- Typed Core contracts and Domain values at the host boundary. Provider JSON never leaves the Infrastructure layer.
- UBL 2.1 reading and writing, local XSD, CEN and Peppol Schematron validation (Java 11+), bundled code lists.
- Verified mapping of Peppol webhook events, including signing-secret rotation. The host owns HTTP delivery and replay policy.
- `FailureClassifier` maps any failure to a category, retryability and unknown-outcome flag; the offline `TestingFakeSapiClient` supports host application tests without a provider.
- An optional Laravel bridge in `integrations/laravel` (service provider, encrypted token cache, shared circuit breaker and backpressure).
- Publish the bridge configuration with `php artisan vendor:publish --tag=efaktura-connector-config`.

The host application owns business workflows, persistence, scheduling and webhook routes.

## Requirements

- PHP 7.4 or newer with `curl`, `ctype`, `dom`, `hash`, `json`, `libxml` and `mbstring`.
- Java 11+, `proc_open` and `flock` for local Schematron validation; `flock` is also used for local Laravel locks when the framework version or cache store lacks atomic locks.

## Installation

```sh
composer require dream1/efaktura-connector
```

## Usage

The participant is passed to each operation; it is not part of global configuration.

```php
use DreaM1\EfakturaConnector\Core\Documents\ListReceivedDocumentsRequest;
use DreaM1\EfakturaConnector\Core\Pagination\PageSize;
use DreaM1\EfakturaConnector\Domain\EFaktura\Participant;

// $sapiClient implements SapiClientInterface and is built by the host application.
$participant = Participant::parse('0245:1234567890');
$page        = $sapiClient->receivedDocumentProvider()->getInboxPage(
    new ListReceivedDocumentsRequest($participant, null, PageSize::fromInt(50))
);
```

## Behavior to know about

- Requests are retried only for GET by default. Document submission carries an explicit idempotency key and is never retried automatically; a network or server failure can leave the outcome unknown. `ProviderApiException::requestMayHaveReachedProvider()` tells you whether it could have arrived.
- The UBL reader does not perform business validation, and the writer serializes the supplied draft without choosing business values.
- Local validation, remote checks, sending, receiving, tracking and webhook registration are separate operations; none triggers another.
- The cURL transport limits a response body to 16 MiB by default (configurable up to 128 MiB).
- Every deliberate exception implements `ConnectorThrowable`; catch that to handle all connector failures. Invalid values raise `InvalidInputException`.

## Development

```sh
composer test
composer check-style
composer check-format
composer analyse
php tools/check-public-api.php
```

See [AGENTS.md](AGENTS.md) for repository conventions, [SECURITY.md](SECURITY.md) for vulnerability reporting and [LICENSE](LICENSE) (MIT).

The Slovak [versioning and compatibility policy](docs/wiki/Versioning-and-Compatibility.md) describes the supported API and breaking-change rules.
