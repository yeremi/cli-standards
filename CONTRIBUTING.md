# Contributing

Thanks for contributing to `yeremi/cli-standards`.

## What Is Helpful

Good contributions for this repository usually fall into one of these categories:

- False positive reports with a minimal reproduction
- False negative reports with a minimal reproduction
- Improvements to sniff precision or error messages
- New sniff proposals tied to a concrete CLI design requirement
- Documentation fixes and usage clarifications

## Before Opening A Pull Request

- Check whether the change is already discussed in an issue
- Keep the change scoped to one problem when possible
- If you change sniff behavior, update or add fixtures under `tests/Fixtures/`
- Decide whether the change can add reports to code that passes today (see below)
- Run `composer test` and `composer test:docs`

## Development Setup

```bash
composer install
composer test
```

## Sniff Changes

If you change an existing sniff or add a new one:

- Preserve backward compatibility where possible
- Prefer reducing false positives over chasing perfect coverage
- Keep the detection logic explicit and reviewable
- Update `README.md` when behavior or usage changes
- Keep the sniff's reference doc in `CLIStandards/Docs/<Category>/<Sniff>Standard.xml` current (a new sniff needs one), then run `composer docs` to regenerate `docs/sniffs.md`; `composer test:docs` fails when a doc is missing or `docs/sniffs.md` is stale
- Only add an autofix (`addFixableError()`/`addFixableWarning()`) when it cannot change behavior, and cover it with a fixture case that has a `fixed` file (see `tests/Fixtures/Autofix/`)

### Does it add reports?

Users gate CI on this standard, so every behavior change is classified by its effect on code that passes today. Follow the release rules in [README: Versioning and upgrades](README.md#versioning-and-upgrades):

- **Only removes reports** (false-positive fix): patch-level. Add it under `Fixed` or `Changed` in `CHANGELOG.md`.
- **Can add reports** (catches previously missed violations, reads more code, new sniff): minor-level at least. Also add an entry under `May add reports` in `[Unreleased]`, describing which code is newly reported.
- **Raises the minimum PHP or PHPCS version**: minor-level. Add a `Requirements` entry in `[Unreleased]`.
- **Renames or removes an error code, changes severity, or changes `CLIStandardsMust`**: major-level. Discuss in an issue first.

New sniffs are added as warnings to `CLIStandards` only.

## Pull Request Notes

Please include:

- What changed
- Why the change is needed
- Any tradeoffs or known limitations
- Fixture or reproduction coverage for the change

## Security

Do not open public issues for security vulnerabilities. Use the process described in `SECURITY.md`.
