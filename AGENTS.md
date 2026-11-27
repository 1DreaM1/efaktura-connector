# Repository Guide for Agents

## Project overview

`dream1/efaktura-connector` is an MIT-licensed, framework-independent PHP library for the Slovak e-invoicing SAPI, Peppol Gateway, and Integrator APIs. Its GitHub repository is `https://github.com/1DreaM1/efaktura-connector`. Public PHP namespaces use the root `DreaM1\EfakturaConnector\`; the optional Laravel bridge uses `DreaM1\EfakturaConnector\Laravel\`. Treat `composer.json` as the source of truth for package name and PSR-4 mappings, and `LICENSE` as the source of truth for the code license. It also provides UBL 2.1 Invoice/CreditNote reading, writing, and local validation. The package is a connector library: the host application owns business workflows, persistence, scheduling, user-facing messages, and webhook delivery routes.

The Composer package requires PHP 7.4 or newer and the `curl`, `ctype`, `dom`, `hash`, `json`, `libxml`, and `mbstring` extensions. Preserve PHP 7.4 compatibility in package code. Laravel is optional; `illuminate/support` is suggested for the bridge, not required by the core. Local Schematron validation additionally needs Java 11 or newer, `proc_open`, `flock`, and a writable temporary directory whose filesystem supports file locks.

## Repository map

- `src/Core/` contains public contracts, configuration, request/result models, exceptions, retry and circuit-breaker abstractions, and UBL/XML contracts.
- `src/Domain/` contains domain values, statuses, code lists, participant identifiers, webhook events, and UBL document/draft models.
- `src/Infrastructure/EFaktura/Api/` contains SAPI, Peppol Gateway, and Integrator API requests, endpoints, authentication, transport, serialization, and logging helpers.
- `src/Infrastructure/EFaktura/Documents/`, `Connection/`, `Webhooks/`, and `Validation/` contain provider adapters and local validation implementations.
- `src/Infrastructure/Ubl/` contains UBL readers, writers, code lists, and validation rules.
- `src/Support/` contains package-wide helper types, including secure XML parsing helpers in `src/Support/Xml/` shared by Core and Infrastructure.
- `integrations/laravel/` contains the optional Laravel service provider and encrypted Laravel cache adapter for SAPI tokens.
- `config/efaktura-connector.php` supplies defaults consumed by the Laravel bridge.
- `resources/validation/` contains versioned UBL XSD, CEN/Peppol Schematron, Saxon runtime files, and notices/licenses.
- `resources/code-lists/` contains bundled reference code-list data used by UBL helpers.
- `tests/Architecture/`, `tests/Unit/`, and `tests/Integration/` protect dependency boundaries, individual components, and connector workflows. `tests/Support/` holds reusable fakes and fixtures.
- `docs/wiki/` contains package guides, primarily in Slovak. `docs/eFaktura/api/openapi.json` is the provider API contract. `README.md`, `README.en.md` and the resource READMEs are other maintained package documentation.
- `tools/` contains repository maintenance scripts (API baseline, validation-resource and upstream-release checks, smoke checks, site/wiki generators); see `tools/README.md`. They are not part of the Composer distribution.
- `web/` is the generated project website and HTML wiki, built by `node tools/build-site.js` and `node tools/build-wiki.js` from `docs/wiki/`. Do not hand-edit generated pages; regenerate and commit them (CI fails when they are stale).

Composer PSR-4 mappings are `DreaM1\\EfakturaConnector\\` to `src/`, `DreaM1\\EfakturaConnector\\Laravel\\` to `integrations/laravel/src/`, and the test namespace `DreaM1\\EfakturaConnector\\Tests\\` to `tests/`.

## Architecture and boundaries

- Keep `Core` and `Domain` independent of Laravel, HTTP clients, and provider-specific transport details. Put API calls and concrete adapters in `Infrastructure`.
- Keep the reusable package independent of any host application's namespace, database, framework workflow, or persistence model. The Laravel bridge is the only Laravel-specific integration area.
- Put application-facing capabilities behind Core contracts and map provider responses to typed package models at the infrastructure boundary. Avoid leaking decoded provider arrays when a typed model or response contract belongs in the public API.
- SAPI provides OAuth tokens used for both SAPI and Peppol Gateway calls. Participant identity is supplied per participant-scoped operation; it is not part of global connector configuration.
- Local UBL parsing, writing, XSD validation, Schematron validation, remote Gateway checks, sending, receiving, tracking, and webhook registration are separate operations. Do not make one implicitly trigger another or add host-owned workflow/persistence to the connector.
- The default cURL transport limits response bodies through `runtime.http.response_max_bytes` (16 MiB by default, configurable up to 128 MiB). An oversized response throws `ProviderResponseTooLargeException` and is not automatically retried.
- Local UBL findings use `error`, `warning`, or `info` severity. Warnings and informational findings do not make `LocalUblValidationResult::isValid()` false; errors and omitted findings do.
- UBL reading is not business validation. The writer serializes the supplied draft and does not silently choose business values or validate the result.
- Sending a document must retain its explicit idempotency and retry semantics. A network/server failure can leave the result unknown; do not make document submission automatically retryable. `ApiRequest` defaults retries to GET only; opt another request into retry only when its operation is safe.
- The host application is responsible for verifying webhook signatures before trusting payloads. It owns HTTP delivery, signing-secret selection, freshness and replay policy, event deduplication, and durable event handling. The connector has no HTTP receiver, and generic payload parsing does not authenticate a delivery. Its provider-specific factory with an explicitly supplied verifier is an optional helper that verifies the raw signed body before mapping supported events; the host must invoke it or perform equivalent verification before handling a signed delivery.
- Exception messages are technical English for diagnostics. Do not turn them into localized user-facing text in the library.
- Reject invalid values with `Support\Exceptions\InvalidInputException`, never a bare `InvalidArgumentException`. Every deliberate connector exception implements `Support\Exceptions\ConnectorThrowable`; keep new exception types inside that family. Never throw a bare SPL exception (`LogicException`, `RuntimeException`, ...): use `InvalidInputException` for caller values, `ConfigurationException` for setup the administrator can fix, and `Support\Exceptions\InvariantViolationException` for broken internal invariants. Architecture tests enforce both rules.

## Code conventions

- Follow the existing PSR-4 directory/namespace layout and class-per-file naming. PHP classes use `PascalCase`, methods and properties use `camelCase`, and constants use uppercase names with underscores.
- Name provider API endpoint classes action-first as `<Verb><Resource>Endpoint` within their provider namespace, and name multi-field Core operation inputs `<Verb><Resource>Request`. Endpoint classes extend `AbstractEndpoint`, build list queries with `PageQuery`, and stay non-final because adapter tests mock them (an architecture test enforces the naming, base class and `send()` rules). Pass Core requests or narrow typed values directly to endpoints; do not add duplicate Infrastructure `*EndpointRequest` wrappers. Keep provider field names and wire serialization in Infrastructure. The OAuth token exchange input is transport-specific and is named `OAuthTokenRequest`.
- Endpoints return the Core model (or `bool`/`void` when the operation has no richer result), never a decoded provider array or a schema-shaped DTO. Map each provider response to its Core model in a static `<Resource>Factory` (or `<Resource>Mapper`) in `Api/Response/`, named after the Core result (`DocumentSubmissionResultFactory`, `ParticipantProfileFactory`), with a `SCHEMA` constant holding the OpenAPI schema name used in `InvalidProviderResponseException` messages. Keep a separate response DTO with `ReadsProviderResponse` only for transport-internal values that have no Core model (the OAuth `SapiTokenResponse`). Build request bodies from Core requests in `Api/Serialization/<Provider>RequestMapper` classes, not inline in endpoints.
- Follow `.editorconfig`: use UTF-8, LF line endings, and a final newline; PHP files use four spaces and trailing whitespace is trimmed. Markdown explicitly disables trailing-whitespace trimming, so preserve intentional Markdown line breaks.
- Match the existing PHP style: namespace declarations, explicit imports, native parameter/return types, typed properties, and docblocks for array shapes/generic element types where useful. Existing code does not declare `strict_types`; do not add it inconsistently as part of an unrelated change.
- Keep value objects and request builders immutable where the surrounding API uses copy-returning `with*()` methods. Preserve validation and normalization behavior at object boundaries.
- Do not use syntax or standard-library features newer than PHP 7.4 in package code (for example union types, attributes, enums, `match`, or constructor property promotion).
- Keep comments and exception text concise and technical. Preserve the established Slovak language and existing structure when editing Slovak README/wiki content; update the relevant guide when public behavior, configuration, API operations, or validation artifacts change.

## API and validation changes

- Check `docs/eFaktura/api/openapi.json` before changing provider routes, request fields, response fields, or status behavior. Keep endpoint/request/response mapping in the corresponding `src/Infrastructure/EFaktura/Api/` subdirectories and update `docs/wiki/API-Reference.md` and related guides for public changes.
- Reuse `ApiRequest` for routes, URL-encoded path parameters, query parameters, headers, bodies, participant identity, request context, and retry safety. Keep request encoding and response validation behavior consistent with the existing API client.
- Preserve separation between SAPI endpoints and `PeppolGateway` endpoints. Add/update the matching adapter and Core contract only when the capability is part of the package's application-facing API.
- For validation resource changes, follow `resources/validation/README.md`. Keep artifacts versioned, preserve notices/licenses, and keep resource versions and filenames synchronized with `ValidationResources` and relevant tests. Do not hand-edit generated Schematron XSLT as a substitute for updating its source rules.
- For bundled code-list changes, follow `resources/code-lists/README.md` and keep resource names synchronized with `CodeListResources` and relevant tests.
- Treat credentials, access tokens, document XML, and provider payloads as sensitive. Preserve existing redaction/sanitization behavior and avoid adding secrets or real document data to fixtures or logs.

## Tests and available commands

`phpunit.xml.dist` bootstraps `vendor/autoload.php`; its default `EFaktura Connector` suite discovers `*Test.php` files under `tests/`, excluding `tests/Laravel/`. Those bridge tests form the separate `Laravel Bridge` suite, run in CI on PHP 7.4 against the locked Laravel 8 dev dependency and, via `tools/test-laravel-bridge.ps1`, on Laravel 13 / PHP 8.4. PHPUnit `^9.6` is the development test dependency so the main suite can run on PHP 7.4. Composer scripts are:

```sh
composer test
composer check-style
composer format
composer check-format
composer analyse
```

After installing dependencies with `composer install`, a focused test can be run with:

```sh
vendor/bin/phpunit --configuration=phpunit.xml.dist tests/Unit/Infrastructure/EFaktura/Api/ApiRequestTest.php
```

Use the nearest existing test layer and naming pattern for behavior changes:

- Unit tests belong under the matching `tests/Unit/` area.
- Integration tests use synthetic responses/fake HTTP executors and must not contact a real provider.
- Architecture tests enforce package dependency boundaries; update them deliberately if a boundary changes.
- Mock Core contracts for host-level behavior, endpoint classes for adapter behavior, and `HttpRequestExecutorInterface`/test transports to exercise full connector HTTP flows.
- Schematron execution tests need Java 11+, the bundled Saxon resources, and `proc_open`; tests may skip execution-dependent cases when those are unavailable.

PHP_CodeSniffer checks PHP style with the project rules in `phpcs.xml.dist`. PHP-CS-Fixer formats maintained PHP files using `.php-cs-fixer.dist.php`; run `composer format` to apply formatting and `composer check-format` to check it without changing files. PHPStan (`composer analyse`, configured in `phpstan.neon.dist`) analyses `src/` and `integrations/laravel/src/` at level 8 for PHP 7.4 with `treatPhpDocTypesAsCertain: false`, because library inputs may not honor docblock types. Errors present when it was introduced are recorded in `phpstan-baseline.neon`; new code must not add entries. Fix a baselined error when touching its code and remove its entry (PHPStan reports entries that no longer match). Do not add other formatters or static-analysis tools by assumption. Report which checks were run and any environment limitations when verification is part of the task.

## Working-tree care

Before editing, inspect the current working-tree state and relevant files. Keep changes scoped to the requested task, preserve unrelated user edits, and avoid reverting or overwriting existing modifications. Do not send requests to production provider APIs or submit real invoices during development work.
