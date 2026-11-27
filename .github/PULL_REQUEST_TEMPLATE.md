## What changes and why

<!-- Describe the change and link the related issue. -->

## Checklist

- [ ] Tests added or updated; no test contacts a real provider.
- [ ] `composer test`, `composer check-style`, `composer check-format` and `composer analyse` pass.
- [ ] Public API changes are reviewed (`tests/Architecture/public-api.json`) and noted in `CHANGELOG.md`.
- [ ] Wiki updated and `web/` regenerated (`node tools/build-wiki.js`, `node tools/build-site.js`) if behaviour,
      configuration or API operations changed.
- [ ] No credentials, tokens, real invoices or personal data in code, fixtures or logs.
- [ ] A single `release:patch`, `release:minor` or `release:major` label is set if this should create a release.
