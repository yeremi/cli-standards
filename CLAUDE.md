# cli-standards

PHPCS sniffs for CLI command design quality (Composer package `yeremi/cli-standards`, PHP ≥ 7.4). Standards live in `CLIStandards/` and `CLIStandardsMust/`; fixtures in `tests/Fixtures/`.

## Commands
- `composer test` — sniff unit tests (PHPUnit, per line) + ruleset end-to-end checks
- `composer test:update` — regenerate unit test expectations after changing fixtures (review the diff)
- `composer test:fixtures:verbose` — ruleset checks with details
- `composer test:docs` — sniff docs complete and `docs/sniffs.md` current
- `composer validate --strict` — also run in CI

Keep code compatible with PHP 7.4 (no enums, readonly, match-only syntax, etc.). Every new or changed sniff needs a fixture under `tests/Fixtures/`. Update `CHANGELOG.md` for user-visible changes.
