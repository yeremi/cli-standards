# Changelog

All notable changes to this project will be documented in this file.

The format is based on Keep a Changelog and this project follows Semantic Versioning.

## [Unreleased]

### Added

- `ignoreMethods` ruleset property on every command-aware sniff to exclude public lifecycle methods by name (#2)
- Documentation fixtures, including pass cases (#2)
- Arguments fixtures (#3)
- `unboundedSources` ruleset property on `ProgressBar` (#4)
- Scalability fixtures, including pass cases (#4)

### Changed

- Command detection is shared by all command-aware sniffs (`DocblockOptions`, `DocblockExamples`, `SinceAnnotation`, `ConfirmOnDelete`, `DryRunSupport`, `FormatSupport`, `AvailableFields`, `BatchSizeFlag`). Only public, non-magic methods (plus `__invoke`) that do more than register commands are checked, which removes false positives on constructors, registration bootstraps and private helpers (#2)
- `ProgressBar` skips loops over array literals and constants, and message-only loops (`error`/`warning`/`line`) unless they iterate an unbounded source such as `get_posts()` (#4)
- Fixture suite asserts message counts per sniff source, not only totals (#1)

### Fixed

- `$assocArgs` (camelCase) and other names for the second handler parameter are recognized as WP-CLI assoc args by the Documentation, `FlagNaming` and `FlagExtraction` sniffs (#3)

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
