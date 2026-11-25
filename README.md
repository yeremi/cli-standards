# CLIStandards PHPCS Standard

PHPCS sniffs for CLI command design quality, currently focused on WP-CLI command code.

This package publishes the `CLIStandards` coding standard as a Packagist-ready Composer package. It enforces 18 checks derived from the CLI Standards model:

- 8 blocking checks reported as errors
- 10 advisory checks reported as warnings

## Installation

```bash
composer require --dev yeremi/cli-standards
```

The Composer installer plugin registers the standards automatically. After installation:

```bash
vendor/bin/phpcs -i
```

You should see `CLIStandards` and `CLIStandardsMust` in the installed standards list.

## Usage

Run the standard against your CLI command code:

```bash
vendor/bin/phpcs --standard=CLIStandards path/to/cli/
```

Useful variants:

```bash
# Show sniff codes.
vendor/bin/phpcs --standard=CLIStandards -s path/to/cli/

# Enforce only MUST-level checks.
vendor/bin/phpcs --standard=CLIStandards --warning-severity=0 path/to/cli/

# Emit JSON for CI tooling.
vendor/bin/phpcs --standard=CLIStandards --report=json path/to/cli/

# Run only blocking rules.
vendor/bin/phpcs --standard=CLIStandardsMust path/to/cli/
```

`CLIStandardsMust` is intended to behave like `CLIStandards` with warnings suppressed, which makes it useful for staged CI adoption.

## Example Output

Anonymized example from a real local scan:

```text
FILE: sample-plugin/includes/cli/class-command.php
----------------------------------------------------------------------
FOUND 4 ERRORS AND 3 WARNINGS AFFECTING 7 LINES
----------------------------------------------------------------------
  9 | ERROR   | Command name "sample-command" must include a namespace
    |         | (CLIStandards.CommandStructure.CommandRegistration.MissingNamespace)
 24 | ERROR   | Command docblock must contain a "## OPTIONS" section
    |         | (CLIStandards.Documentation.DocblockOptions.MissingOptionsSection)
 25 | ERROR   | Command docblock must contain a "## EXAMPLES" section
    |         | (CLIStandards.Documentation.DocblockExamples.MissingExamplesSection)
 48 | WARNING | Consider using WP_CLI\Utils\get_flag_value()
    |         | (CLIStandards.Arguments.FlagExtraction.DirectAccess)
 77 | WARNING | Bulk loop should use make_progress_bar()
    |         | (CLIStandards.Scalability.ProgressBar.MissingProgressBar)
----------------------------------------------------------------------
```

## What It Enforces

Error-level sniffs:

- `CommandRegistration`
- `FlagNaming`
- `OutputMethod`
- `ErrorSeverity`
- `ConfirmOnDelete`
- `ConfirmPassthrough`
- `DocblockOptions`
- `DocblockExamples`

Warning-level sniffs:

- `FlagExtraction`
- `StdoutStderr`
- `FormatSupport`
- `AvailableFields`
- `WpErrorHandling`
- `DryRunSupport`
- `BatchProcessing`
- `ProgressBar`
- `BatchSizeFlag`
- `SinceAnnotation`

The implementation is intentionally heuristic in some areas. It favors practical signal over deep semantic analysis, so some checks can still produce false negatives or advisory-only warnings.

## Scope

The standard name is generic, but the current implementation is not framework-agnostic. The sniffs are tuned for codebases that expose commands through `WP_CLI`, and most checks activate only when `WP_CLI` usage is detected.

### What counts as a command

Sniffs that check command methods (documentation, safety, output format, batch size) follow WP-CLI's own rules for subcommands. A method is treated as a command handler when:

- it is `public` (or a plain function outside a class);
- it is not a magic method, except `__invoke`;
- it does more than register commands (a method whose only WP-CLI calls are `WP_CLI::add_command()` / `add_hook()` is a bootstrap);
- it uses WP-CLI (`WP_CLI::…`, `WP_CLI\…`) or takes `$assoc_args` as its second parameter.

## Configuration

Public methods of a registered command class are subcommands in WP-CLI, so `run()` or `services()` are checked like any other command. If a command class has public lifecycle methods that are never invoked from the command line, exclude them by name (case-insensitive) on any command-aware sniff:

```xml
<rule ref="CLIStandards.Documentation.DocblockOptions">
    <properties>
        <property name="ignoreMethods" type="array">
            <element value="services"/>
        </property>
    </properties>
</rule>
```

## Add To `phpcs.xml.dist`

```xml
<?xml version="1.0"?>
<ruleset name="MyPluginStandard">
    <description>Project coding standards.</description>

    <rule ref="CLIStandards"/>

    <file>./includes/cli/</file>
    <exclude-pattern>*/vendor/*</exclude-pattern>
</ruleset>
```

## Development

Install dependencies:

```bash
composer install
```

Run the fixture-based regression suite:

```bash
composer test
```

For contribution workflow and expectations, see `CONTRIBUTING.md`.

## Repository Layout

- `CLIStandards/`: the main standard and sniff classes
- `CLIStandards/Helpers/`: shared base class for command-aware sniffs (command detection, docblock lookup)
- `CLIStandardsMust/`: blocking-only variant for staged adoption
- `tests/Fixtures/`: regression fixtures for expected violations and clean cases
- `scripts/verify-fixtures.php`: assertion-based test runner used by CI

## License

See LICENSE ([MIT](LICENSE)).
