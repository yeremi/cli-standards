# Changelog

All notable changes to this project will be documented in this file.

The format is based on Keep a Changelog and this project follows Semantic Versioning.

## [Unreleased]

### May add reports

Detection fixes in this release can report violations in code that passed with 1.0.0:

- Methods recognized as commands by their `$assoc_args` parameter or by the `WP_CLI\…` namespaced API, without any `WP_CLI::` call, are now checked by `ConfirmOnDelete`, `DryRunSupport`, `FormatSupport` and `BatchSizeFlag` (#2)
- Handlers using `$assocArgs` or another name for the second parameter are now checked by `FlagNaming`, `FlagExtraction` and the Documentation sniffs (#3)
- `ProgressBar` no longer counts a progress bar created in a neighboring method (#5)
- A docblock placed before a PHP 8 attribute is now read: its `MissingDocblock` error becomes `MissingOptionsSection`, `MissingExamplesSection` or `MissingSince` when those parts are missing (#6)
- `## OPTIONS`, `## EXAMPLES` and `## AVAILABLE FIELDS` only count as headings on their own line, not when mentioned in prose (#13)
- `ConfirmOnDelete` and `DryRunSupport` also report commands that call content-destroying APIs (`wp_delete_post()`, `wp_delete_user()`, `$wpdb->delete()`, `$wpdb->query()` with `DELETE`/`TRUNCATE`/`DROP`, ...) whatever their name (#15)
- Closures and arrow functions passed directly to `WP_CLI::add_command()` are now commands: the Documentation, Safety, Output, `BatchSizeFlag` and Arguments sniffs check them (#19)
- Command keywords are matched against the name users type: a `@subcommand` annotation or the name registered with `WP_CLI::add_command()` in the same file can make a command destructive, state-changing, listing or bulk (#15)

Reports are removed for constructors, registration bootstraps, private/protected helpers (#2), loops over fixed collections or message-only loops (#4), and methods whose name only contains a keyword as part of another word, such as `dropdown_stats` ("drop") or `widget_getter` ("get") (#15), commands that confirm, handle dry-run, format output or read the batch size in a same-class helper (#16), and commands documented through the `synopsis`/`longdesc` arguments of `WP_CLI::add_command()` instead of a docblock (#19), and `WpErrorHandling` reports on functions that cannot return `WP_Error` in that call (#25).

### Added

- `ignoreMethods` ruleset property on every command-aware sniff to exclude public lifecycle methods by name (#2)
- Documentation fixtures, including pass cases (#2)
- Arguments fixtures (#3)
- `unboundedSources` ruleset property on `ProgressBar` (#4)
- `destructiveFunctions` ruleset property on `ConfirmOnDelete` and `DryRunSupport` (#15)
- Classification fixtures (#15)
- `@cli-standards confirm-delegated|dry-run-delegated|format-delegated <reason>` docblock tags to declare delegation to other classes (#16)
- Delegation fixtures (#16)
- Registration fixtures (#19)
- ErrorHandling fixtures (#25)
- Confirm fixtures (#31)
- CommandStructure fixtures (#32)
- Scalability fixtures, including pass cases (#4)

### Changed

- Command detection is shared by all command-aware sniffs (`DocblockOptions`, `DocblockExamples`, `SinceAnnotation`, `ConfirmOnDelete`, `DryRunSupport`, `FormatSupport`, `AvailableFields`, `BatchSizeFlag`). Only public, non-magic methods (plus `__invoke`) that do more than register commands are checked, which removes false positives on constructors, registration bootstraps and private helpers (#2)
- `ConfirmOnDelete`, `DryRunSupport`, `FormatSupport`, `AvailableFields` and `BatchSizeFlag` match keywords as whole words of the command name instead of substrings of the method name (#15)
- `DocblockOptions` accepts a `synopsis` (or an `## OPTIONS` heading in `longdesc`) passed to `WP_CLI::add_command()`, and `DocblockExamples` an `## EXAMPLES` heading in `longdesc` (#19)
- `ConfirmOnDelete`, `DryRunSupport`, `FormatSupport` and `BatchSizeFlag` also look for `WP_CLI::confirm()`, dry-run handling, `format_items()` and batch-size handling in same-class methods the command calls (`$this->…()`, `self::…()`, `static::…()`), up to two levels (#16)
- `ProgressBar` skips loops over array literals and constants, and message-only loops (`error`/`warning`/`line`) unless they iterate an unbounded source such as `get_posts()` (#4)
- Fixture suite asserts message counts per sniff source, not only totals (#1)

### Fixed

- `DocblockOptions`, `DocblockExamples` and `AvailableFields` only accept their section as a heading on its own line; a mention inside prose (e.g. "see ## OPTIONS") no longer satisfies the check (#13)
- `$assocArgs` (camelCase) and other names for the second handler parameter are recognized as WP-CLI assoc args by the Documentation, `FlagNaming` and `FlagExtraction` sniffs (#3)
- `ProgressBar` looks for `make_progress_bar()` only inside the enclosing function, so a progress bar in a neighboring method no longer hides a missing one (#5)
- Docblocks followed by PHP 8 attributes (`#[...]`) are now found, removing false `MissingDocblock` errors (#6)
- `WpErrorHandling` no longer reports functions that never return `WP_Error` (`wp_delete_post()`, `wp_delete_user()`, `wp_create_category()`, `wp_upload_bits()`, `wp_handle_upload()`), and only reports `wp_insert_post()`, `wp_update_post()` and `wp_insert_attachment()` when their `$wp_error` argument is passed and not `false` (#25)
- `ErrorSeverity` ignores `exit`/`die` at file scope, such as the `if ( ! defined( 'ABSPATH' ) ) { exit; }` direct-access guard (#29)
- Methods that call `WP_CLI::add_command()` and take no assoc-args parameter are registration bootstraps, not commands, even when they also log; `WP_CLI::class` no longer counts as a WP-CLI call; `ProgressBar` ignores loops that only register commands (#30)
- `ConfirmPassthrough` accepts `WP_CLI::confirm( $msg )` inside an `if` that checks the `yes` flag on the assoc args, e.g. `if ( ! isset( $assoc_args['yes'] ) )` (#31)
- `CommandRegistration` accepts a single-word name for a class command group (`WP_CLI::add_command( 'acme', Acme_Command::class )`), since its methods are the namespaced subcommands; it still reports groups named after a WP-CLI core command such as `user` or `cache` (#32)
- `OutputMethod` only reports `echo`/`print` in command handlers and the same-class methods they call, not in web code (admin notices, screens) of files that also register commands (#33)

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
