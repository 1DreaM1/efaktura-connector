# Contributing

Thank you for helping with `dream1/efaktura-connector`. The project is a framework-independent PHP library, so
changes should keep the core free of host application code and compatible with PHP 7.4.

## Before you start

- Open an issue first for larger changes or new public API, so the direction can be agreed.
- Report vulnerabilities privately as described in [SECURITY.md](../SECURITY.md), never in a public issue.
- Never include real credentials, access tokens, invoices, personal data or unredacted provider logs. Use
  synthetic data in issues, tests and fixtures.

## Development

```sh
composer install
composer test
composer check-style
composer check-format
composer analyse
```

- Follow the conventions in [AGENTS.md](../AGENTS.md): PSR-4 layout, no syntax newer than PHP 7.4, immutable value
  objects, and exceptions from the connector family (`InvalidInputException`, `ConfigurationException`,
  `InvariantViolationException`).
- Add or update tests in the nearest existing layer (`tests/Unit`, `tests/Integration`, `tests/Architecture`).
  Tests must not contact a real provider.
- Public API changes need a reviewed update of `tests/Architecture/public-api.json`
  (`php tools/check-public-api.php --update`) and a note in `CHANGELOG.md`.
- Update the Slovak wiki in `docs/wiki/` when public behaviour, configuration or API operations change, then run
  `node tools/build-wiki.js` and `node tools/build-site.js` and commit the regenerated `web/` files. CI fails when
  they are stale.

## Pull requests

- Keep a pull request focused on one change and describe what it changes and why.
- All checks in the `Tests` and `Security` workflows must pass.
- To request a release when the pull request is merged, add exactly one label: `release:patch`, `release:minor` or
  `release:major`. Pull requests without a release label do not create a release.

By contributing you agree that your contribution is licensed under the [MIT License](../LICENSE).
