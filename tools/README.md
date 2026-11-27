# Repository tools

Maintenance scripts, run from the repository root. They are excluded from the Composer distribution.

| Script | Purpose |
| --- | --- |
| `php tools/check-public-api.php` | Compares Core, Domain and `@api` declarations with the reviewed baseline in `tests/Architecture/public-api.json`. Use `--update` only after reviewing an intentional public API change. |
| `php tools/check-validation-resources.php` | Checks the validation manifest, bundled artifact hashes, inventories and version assertions. |
| `php tools/check-upstream-rule-releases.php` | Compares the manifest with upstream releases over HTTPS and prints differences as JSON (used by the weekly workflow). |
| `composer test:mutation` | Mutation testing with the locked Infection (`infection/infection`, `infection.json5`, covers `src/` except `Testing`). Needs a coverage driver, for example `XDEBUG_MODE=coverage` with Xdebug or PCOV; narrow a run with `-- --filter=<File.php>`. CI runs it weekly in `quality-deep.yml` with the same locked version. |
| `./tools/test-composer-package.ps1` | Composer archive consumer smoke check (below). |
| `./tools/test-laravel-bridge.ps1` | Laravel 13 bridge integration checks (below). |
| `php tools/simulate-sapi-submission-retries.php` | SAPI submission retry simulations (below). |
| `node tools/build-site.js`, `node tools/build-wiki.js` | Regenerate `web/` and `web/wiki/` (below). |

## Composer archive consumer smoke check

From the repository root, run `./tools/test-composer-package.ps1`. The script copies the maintained distribution inputs
to a temporary package, creates a ZIP artifact, and installs that artifact into a clean Composer application with
Packagist disabled. It then checks runtime platform requirements, Core autoloading, and bundled UBL validation
resources. The check uses the local PHP and Composer executables and removes its temporary files when it finishes.

## Laravel 13 bridge integration checks

Run the isolated bridge tests from the repository root:

```powershell
powershell.exe -NoProfile -ExecutionPolicy Bypass -File .\tools\test-laravel-bridge.ps1
```

The dedicated Composer project and lock file are in `tools/laravel-bridge/`. It pins Laravel Framework 13 and PHPUnit with their transitive dependencies. The script downloads the latest stable Composer PHAR to its temporary directory and verifies its SHA-256, so it does not depend on a global Composer installation. It installs dependencies into a unique temporary vendor directory, checks the PHP platform requirements, runs the Laravel bridge tests and public API stability check, then removes the temporary directory. Use PHP 8.4 or newer for Laravel 13. Pass `-KeepTemp` to retain temporary files for inspection. The main test job checks the framework-independent package on PHP 7.4.

This check does not change the package's root `composer.json` or `composer.lock`. Network access to Composer's download host and package repositories is required when the locked archives are not already cached.

## SAPI submission retry simulations

From the repository root, run `php tools/simulate-sapi-submission-retries.php`. It runs the invoice example from `docs/wiki/UBL-Documents.md` in twenty-six isolated scenarios. Tracking responses are configured as status sequences, so a scenario can cover mixed outcomes such as a rate limit followed by recovery. The cases cover 2xx and 3xx boundaries, client, rate-limit, timeout (408) and provider errors (500, 503), repeated timeouts with and without a known provider ID, payload revisions, failed persistence after SAPI acceptance, a rejected SAPI replay, and concurrent retries. It verifies call order, provider IDs, persisted status, and that retries retain the saved payload and idempotency key. The script does not contact a provider and requires the local validation prerequisites documented in the UBL guide, including `pdo_sqlite` and Java 11+. The main GitHub Actions job runs it on PHP 7.4 with Java 11.

`node tools/build-site.js` regenerates the static marketing/overview pages in `web/` (the wiki is generated separately, see below). Run it after editing the site generator and commit the regenerated `web/` files.

`node tools/build-wiki.js` regenerates `web/wiki/`, the encyclopedia-style HTML wiki rendered from `docs/wiki/*.md` with the vendored `tools/vendor/marked.umd.js`. Page order and groups come from `docs/wiki/_Sidebar.md`; the contents box, backlinks, last-edit dates from git and the full-text index `web/wiki/assets/search-index.js` are generated. `tools/wiki-text.js` only splits long paragraphs, list items and table cells into shorter sentence groups and the generator turns key sentences into note/warning/tip callouts; the Markdown sources are never changed. The CSS and JS in `web/wiki/assets/` are edited by hand. Run it after editing the wiki and commit the regenerated files.
