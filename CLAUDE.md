# cli-standards

PHPCS sniffs for CLI command design quality (Composer package `yeremi/cli-standards`, PHP ≥ 7.4). Standards live in `CLIStandards/` and `CLIStandardsMust/`; fixtures in `tests/Fixtures/`.

## Commands
- `composer test` — verify sniffs against fixtures (`scripts/verify-fixtures.php`)
- `composer test:fixtures:verbose` — same, with details
- `composer validate --strict` — also run in CI

Keep code compatible with PHP 7.4 (no enums, readonly, match-only syntax, etc.). Every new or changed sniff needs a fixture under `tests/Fixtures/`. Update `CHANGELOG.md` for user-visible changes.
