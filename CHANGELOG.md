# Changelog

All notable changes to this project will be documented in this file.

The format is based on Keep a Changelog and this project follows Semantic Versioning.

## [Unreleased]

### Changed

- Fixture suite asserts message counts per sniff source, not only totals (#1)

## [1.0.0] - 2026-04-23

### Added

- Initial public release of the `CLIStandards` PHPCS standard
- `CLIStandardsMust` variant for blocking-only adoption
- 18 sniffs covering command structure, arguments, output, error handling, safety, scalability, and documentation
- Fixture-based regression suite with `composer test`
- GitHub Actions CI workflow for validation on multiple PHP versions
- Project metadata and community files for public distribution:
  `README.md`, `LICENSE`, `CODE_OF_CONDUCT.md`, `SECURITY.md`, and `CONTRIBUTING.md`
- Issue and pull request templates for bug reports, sniff proposals, and contribution workflow

### Validated

- Installation and registration through Composer as a PHPCS standard package
- Real-world scans on 10 local WP-CLI plugin repositories
- Equivalence between `CLIStandardsMust` and `CLIStandards --warning-severity=0`
