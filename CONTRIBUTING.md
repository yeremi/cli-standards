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
- Run `composer test`

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

## Pull Request Notes

Please include:

- What changed
- Why the change is needed
- Any tradeoffs or known limitations
- Fixture or reproduction coverage for the change

## Security

Do not open public issues for security vulnerabilities. Use the process described in `SECURITY.md`.
