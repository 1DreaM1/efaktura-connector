# Changelog

## Unreleased

## 0.1.0 - 2026-11-27

First release.

- SAPI-SK, Peppol Gateway and Integrator API clients with typed Core contracts: sending, receiving, tracking, discovery, webhook registration and Integrator account management.
- OAuth token handling, cURL transport with retry, circuit breaker, backpressure, execution locks and redacted logging. Document submission is never retried automatically.
- UBL 2.1 Invoice and CreditNote reader, writer and draft models; local XSD, Peppol Billing and Schematron validation (Java 11+); bundled code lists.
- Webhook event mapping with a host-supplied signature verifier.
- `FailureClassifier` for retry decisions and `Testing\FakeSapiClient` for offline host tests.
- Optional Laravel bridge (Laravel 5.6 and 8.83 to 13): named clients, encrypted token cache, shared locks, `php artisan efaktura:check`, and `vendor:publish --tag=efaktura-connector-config`.

Requires PHP 7.4 or newer.
